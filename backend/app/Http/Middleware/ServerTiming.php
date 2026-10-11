<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds `Server-Timing: app;dur=…, db;dur=…;desc="N queries"` to API responses,
 * so slow requests can be diagnosed from the browser's network panel.
 */
class ServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $dbMs = 0.0;
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$dbMs, &$queries) {
            $dbMs += $query->time;
            $queries++;
        });

        $response = $next($request);

        $appMs = (microtime(true) - $started) * 1000;
        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%.1f, db;dur=%.1f;desc="%d queries"',
            $appMs,
            $dbMs,
            $queries,
        ));

        return $response;
    }
}
