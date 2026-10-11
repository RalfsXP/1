<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    private function spa(User $user): static
    {
        return $this->actingAs($user)->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['health.admin_emails' => ['admin@example.com']]);
    }

    public function test_every_signed_in_user_sees_site_health_without_internals(): void
    {
        $this->getJson('/api/admin/health')->assertUnauthorized();

        $response = $this->spa(User::factory()->create(['email' => 'someone@example.com']))
            ->getJson('/api/admin/health')
            ->assertOk()
            ->assertJsonPath('can_migrate', false)
            ->assertJsonPath('database.status', 'ok')
            ->assertJsonPath('database.host', null)
            ->assertJsonPath('migrations.status', 'ok');

        $this->assertSame([null], array_values(array_unique(array_column($response->json('database.tables'), 'rows'))));
        $this->assertSame([null], array_values(array_unique(array_column($response->json('checks'), 'value'))));
    }

    public function test_admins_can_run_pending_migrations_and_others_cannot(): void
    {
        $this->spa(User::factory()->create(['email' => 'someone@example.com']))
            ->postJson('/api/admin/migrate')
            ->assertNotFound();

        // Leave the newest migration pending, then run it from the endpoint.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true]);
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->spa($admin)->getJson('/api/admin/health')->assertJsonPath('migrations.status', 'warning');

        $this->spa($admin)->postJson('/api/admin/migrate')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('migrations.status', 'ok')
            ->assertJsonPath('migrations.pending', []);
    }

    public function test_the_user_payload_says_whether_they_are_an_admin(): void
    {
        $this->spa(User::factory()->create(['email' => 'Admin@Example.com']))
            ->getJson('/api/user')
            ->assertJsonPath('is_admin', true);

        $this->spa(User::factory()->create())->getJson('/api/user')->assertJsonPath('is_admin', false);
    }

    public function test_site_health_reports_api_database_migrations_and_checks(): void
    {
        config(['app.frontend_url' => 'http://localhost:5173', 'sanctum.stateful' => ['localhost:5173']]);

        $response = $this->spa(User::factory()->create(['email' => 'admin@example.com']))
            ->getJson('/api/admin/health')
            ->assertOk()
            ->assertJsonPath('can_migrate', true)
            ->assertJsonPath('api.status', 'ok')
            ->assertJsonPath('database.status', 'ok')
            ->assertJsonPath('database.limit_bytes', 500 * 1024 * 1024)
            ->assertJsonPath('migrations.status', 'ok')
            ->assertJsonPath('migrations.pending', [])
            ->assertJsonPath('database.tables.0', ['name' => 'users', 'rows' => 1, 'bytes' => null]);

        $this->assertSame($response->json('migrations.total'), $response->json('migrations.ran'));
        $this->assertGreaterThan(0, $response->json('migrations.total'));
        $this->assertContains('goals', array_column($response->json('database.tables'), 'name'));
        $this->assertNotNull($response->json('database.tables.0.rows'), 'admins see row counts');
        $this->assertEqualsCanonicalizing(
            ['debug_off', 'app_key', 'sanctum', 'https', 'session', 'mail', 'db_ssl'],
            array_column($response->json('checks'), 'key'),
        );
        $checks = collect($response->json('checks'))->keyBy('key');
        $this->assertTrue($checks['sanctum']['ok'], 'host:port must match a Sanctum stateful domain');
        // Nothing secret leaks into the payload.
        $this->assertStringNotContainsString((string) config('app.key'), $response->getContent());
    }
}
