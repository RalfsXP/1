<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Overview of the running system for every signed-in user (admins see a few
 * more internals and can run migrations): API, database (Supabase),
 * migrations, storage use against the free-plan limit, platform status and
 * configuration checks. Every probe is isolated, so one failure is reported
 * instead of breaking the page. Never returns secrets.
 */
class SystemHealthController extends Controller
{
    /** Tables shown in the storage breakdown. */
    private const TABLES = ['users', 'goals', 'goal_completions', 'migrations'];

    public function __invoke(Request $request): JsonResponse
    {
        $admin = (bool) $request->user()->is_admin;
        $started = microtime(true);
        $database = $this->database();
        $checks = $this->checks($database);

        // Non-admins see every status, but not internals: DB host, config values, or row
        // counts (the users table would reveal how many people use the app).
        if (! $admin) {
            $database['host'] = null;
            $database['tables'] = array_map(fn ($t) => [...$t, 'rows' => null], $database['tables'] ?? []);
            $checks = array_map(fn ($c) => [...$c, 'value' => null], $checks);
        }

        return response()->json([
            'checked_at' => now()->toIso8601String(),
            'can_migrate' => $admin,
            'api' => $this->api(),
            'database' => $database,
            'migrations' => $this->migrations(),
            'platforms' => $this->platforms(),
            'checks' => $checks,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    /**
     * Run pending migrations (the "Run migrations" button). Additive-only migrations are a
     * project rule, so this never needs a rollback path; output is returned for the log view.
     */
    public function migrate(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 404);

        try {
            $exit = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            report($e);
            $exit = 1;
            $output = class_basename($e).': '.$e->getMessage();
        }

        return response()->json([
            'ok' => $exit === 0,
            'output' => $output,
            'migrations' => $this->migrations(),
        ], $exit === 0 ? 200 : 500);
    }

    private function api(): array
    {
        $commit = env('VERCEL_GIT_COMMIT_SHA');

        return [
            'status' => 'ok',
            'environment' => config('app.env'),
            'laravel' => Application::VERSION,
            'php' => PHP_VERSION,
            'timezone' => config('app.timezone'),
            // Present when Vercel's system environment variables are exposed.
            'region' => env('VERCEL_REGION'),
            'commit' => $commit ? substr($commit, 0, 7) : null,
            'branch' => env('VERCEL_GIT_COMMIT_REF'),
        ];
    }

    private function database(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $limit = config('health.db_limit_mb') * 1024 * 1024;

        try {
            $t = microtime(true);
            $connection->select('select 1');
            $latency = (int) round((microtime(true) - $t) * 1000);
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'error', 'driver' => $driver, 'error' => class_basename($e), 'limit_bytes' => $limit];
        }

        $result = [
            'status' => 'ok',
            'driver' => $driver,
            'host' => $driver === 'pgsql' ? parse_url((string) config('database.connections.pgsql.url'), PHP_URL_HOST) ?: config('database.connections.pgsql.host') : null,
            'latency_ms' => $latency,
            'version' => $this->attempt(fn () => $driver === 'pgsql'
                ? preg_replace('/^(PostgreSQL [\d.]+).*$/', '$1', $connection->scalar('select version()'))
                : 'SQLite '.$connection->scalar('select sqlite_version()')),
            'size_bytes' => $this->attempt(fn () => $driver === 'pgsql'
                ? (int) $connection->scalar('select pg_database_size(current_database())')
                : $this->sqliteSize()),
            'limit_bytes' => $limit,
            'connections' => $driver === 'pgsql' ? $this->attempt(fn () => [
                'active' => (int) $connection->scalar('select count(*) from pg_stat_activity where datname = current_database()'),
                'max' => (int) $connection->scalar('show max_connections'),
            ]) : null,
            'tables' => [],
        ];

        foreach (self::TABLES as $table) {
            $result['tables'][] = [
                'name' => $table,
                'rows' => $this->attempt(fn () => DB::table($table)->count()),
                'bytes' => $driver === 'pgsql'
                    ? $this->attempt(fn () => (int) $connection->scalar('select pg_total_relation_size(?::regclass)', [$table]))
                    : null,
            ];
        }

        return $result;
    }

    private function migrations(): array
    {
        try {
            $migrator = app('migrator');
            $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));
            $repository = $migrator->getRepository();
            $ran = $repository->repositoryExists() ? $repository->getRan() : [];
            $pending = array_values(array_diff($files, $ran));

            return [
                'status' => $pending ? 'warning' : 'ok',
                'ran' => count(array_intersect($files, $ran)),
                'total' => count($files),
                'pending' => $pending,
            ];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'error', 'ran' => null, 'total' => null, 'pending' => []];
        }
    }

    private function platforms(): array
    {
        $urls = config('health.platforms');
        // Both feeds at once: the slower one sets the wait, capped at 3 s.
        $responses = Http::pool(fn ($pool) => array_map(
            fn ($name) => $pool->as($name)->timeout(3)->acceptJson()->get($urls[$name]),
            array_keys($urls),
        ));

        $out = [];
        foreach (array_keys($urls) as $name) {
            $response = $responses[$name] ?? null;
            $status = $response instanceof \Illuminate\Http\Client\Response && $response->successful() ? $response->json('status') : null;
            $out[] = [
                'name' => $name,
                // Statuspage indicators: none | minor | major | critical.
                'status' => $status === null ? 'unknown' : (($status['indicator'] ?? null) === 'none' ? 'ok' : 'warning'),
                'description' => $status['description'] ?? null,
            ];
        }

        return $out;
    }

    /** Configuration checks; `ok` false means "look at this". Values never include secrets. */
    private function checks(array $database): array
    {
        $production = app()->environment('production');
        // Sanctum compares host[:port], e.g. "merkis.app" or "localhost:5173".
        $url = parse_url((string) config('app.frontend_url'));
        $frontendHost = isset($url['host']) ? $url['host'].(isset($url['port']) ? ':'.$url['port'] : '') : null;
        $stateful = array_map('trim', (array) config('sanctum.stateful'));
        $mailer = config('mail.default');

        return [
            ['key' => 'debug_off', 'ok' => ! ($production && config('app.debug')), 'value' => config('app.debug') ? 'APP_DEBUG=true' : 'APP_DEBUG=false'],
            ['key' => 'app_key', 'ok' => filled(config('app.key')), 'value' => null],
            // The gotcha that broke logins once: the SPA host must be a Sanctum stateful domain.
            ['key' => 'sanctum', 'ok' => $frontendHost && in_array($frontendHost, $stateful, true), 'value' => $frontendHost],
            ['key' => 'https', 'ok' => ! $production || str_starts_with((string) config('app.frontend_url'), 'https://'), 'value' => config('app.frontend_url')],
            ['key' => 'session', 'ok' => ! $production || config('session.driver') !== 'file', 'value' => config('session.driver')],
            ['key' => 'mail', 'ok' => ! $production || ($mailer !== 'log' && filled(config('mail.from.address'))), 'value' => $mailer.' · '.config('mail.from.address')],
            ['key' => 'db_ssl', 'ok' => ($database['driver'] ?? null) !== 'pgsql' || config('database.connections.pgsql.sslmode') === 'require', 'value' => ($database['driver'] ?? null) === 'pgsql' ? 'sslmode='.config('database.connections.pgsql.sslmode') : null],
        ];
    }

    private function sqliteSize(): ?int
    {
        $path = config('database.connections.sqlite.database');

        return $path && $path !== ':memory:' && is_file($path) ? filesize($path) : 0;
    }

    /** Run an optional probe; null when it isn't available (e.g. no permission). */
    private function attempt(callable $probe): mixed
    {
        try {
            return $probe();
        } catch (Throwable) {
            return null;
        }
    }
}
