<?php

namespace Tests\Feature;

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

    public function test_a_user_can_register_and_is_logged_in(): void
    {
        $response = $this->spa()->postJson('/api/register', [
            'name' => 'Ralfs',
            'email' => 'ralfs@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()->assertJsonPath('email', 'ralfs@example.com');
        $this->assertAuthenticated('web');
        $this->assertDatabaseHas('users', ['email' => 'ralfs@example.com']);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->spa()->postJson('/api/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
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
}
