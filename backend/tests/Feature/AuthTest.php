<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** Requests the SPA makes carry its origin, which Sanctum uses to enable session auth. */
    private function spa(): static
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);
    }

    public function test_sign_up_is_closed(): void
    {
        // v1.6: personal app — no public registration endpoint.
        $this->spa()->postJson('/api/register', [
            'name' => 'Ralfs',
            'email' => 'ralfs@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'ralfs@example.com']);
    }

    public function test_a_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->spa()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk()->assertJsonPath('id', $user->id);

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->spa()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->spa()->getJson('/api/user')->assertUnauthorized();

        $user = User::factory()->create();

        $this->actingAs($user, 'web')->spa()->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_a_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->spa()->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_welcome_email_renders_in_latvian_by_default(): void
    {
        $user = User::factory()->create(['name' => 'Ralfs', 'locale' => 'lv']);

        $html = (new WelcomeMail($user))->locale('lv')->render();

        $this->assertStringContainsString('Laipni lūdzam, Ralfs!', $html);
        $this->assertStringContainsString('/email/logo.png', $html);
    }

    public function test_a_user_can_change_their_saved_locale(): void
    {
        $user = User::factory()->create(['locale' => 'lv']);

        $this->actingAs($user, 'web')->spa()
            ->patchJson('/api/user/locale', ['locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('locale', 'en');

        $this->assertSame('en', $user->fresh()->locale);

        $this->actingAs($user, 'web')->spa()
            ->patchJson('/api/user/locale', ['locale' => 'de'])
            ->assertUnprocessable();
    }

    public function test_the_design_switch_is_gone(): void
    {
        // v1.6 has one design (Harbour fog); the old per-user design setting no longer exists.
        $user = User::factory()->create();

        $this->actingAs($user)->spa()->patchJson('/api/user/design', ['design' => 'teal'])
            ->assertNotFound();
    }

    public function test_validation_messages_follow_the_accept_language_header(): void
    {
        $this->spa()->withHeader('Accept-Language', 'lv')
            ->postJson('/api/login', ['email' => '', 'password' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Lauks e-pasts ir obligāts.');

        $this->spa()->withHeader('Accept-Language', 'en')
            ->postJson('/api/login', ['email' => '', 'password' => ''])
            ->assertJsonPath('errors.email.0', 'The email field is required.');
    }
}
