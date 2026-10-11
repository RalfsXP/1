<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('DEMO_PASSWORD');
        parent::tearDown();
    }

    public function test_it_builds_a_full_demo_account_and_can_run_again(): void
    {
        putenv('DEMO_PASSWORD=demo-test-password');

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class); // re-runnable: replaces, never duplicates

        $demo = User::where('email', DemoSeeder::EMAIL)->sole();
        $this->assertSame('Demo', $demo->name);
        $this->assertTrue(Hash::check('demo-test-password', $demo->password));
        $this->assertEqualsCanonicalizing(['day', 'week', 'month', 'year', 'custom'], $demo->goals()->distinct()->pluck('period')->all());
        $this->assertGreaterThan(50, $demo->goals()->withCount('completions')->get()->sum('completions_count'));
        $this->assertSame(2, $demo->whiteboards()->count());

        // The API serves it like any account's data.
        $this->actingAs($demo)
            ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
            ->getJson('/api/goals?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString())
            ->assertOk()
            ->assertJsonFragment(['title' => 'Skriet 5 km']);
    }

    public function test_it_refuses_to_run_without_a_password(): void
    {
        putenv('DEMO_PASSWORD');
        $this->expectExceptionMessage('DEMO_PASSWORD');
        $this->seed(DemoSeeder::class);
    }
}
