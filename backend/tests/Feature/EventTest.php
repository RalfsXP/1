<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function spa(?User $as = null): static
    {
        return $this->actingAs($as ?? $this->user)->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);
    }

    public function test_events_require_authentication(): void
    {
        $this->getJson('/api/events')->assertUnauthorized();
    }

    public function test_a_user_can_create_list_update_and_delete_events(): void
    {
        $id = $this->spa()->postJson('/api/events', [
            'title' => '  Annas dzimšanas diena ',
            'date' => '1995-11-09',
            'repeat' => 'year',
            'badge_color' => '#E5604D',
            'badge_icon' => 'cake',
        ])
            ->assertCreated()
            ->assertJsonPath('title', 'Annas dzimšanas diena')
            ->assertJsonPath('date', '1995-11-09')
            ->assertJsonPath('time', null)
            ->assertJsonPath('repeat', 'year')
            ->assertJsonPath('badge_color', '#e5604d')
            ->json('id');

        $this->spa()->postJson('/api/events', ['title' => 'Zobārsts', 'date' => '1990-01-01', 'time' => '09:15'])
            ->assertCreated()
            ->assertJsonPath('repeat', null);

        // Oldest date first; the frontend sorts by the next occurrence.
        $this->spa()->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.title', 'Zobārsts')
            ->assertJsonPath('1.title', 'Annas dzimšanas diena');

        $this->spa()->patchJson("/api/events/{$id}", ['repeat' => 'month', 'badge_color' => null, 'badge_icon' => null])
            ->assertOk()
            ->assertJsonPath('repeat', 'month')
            ->assertJsonPath('badge_color', null)
            ->assertJsonPath('title', 'Annas dzimšanas diena');

        $this->spa()->deleteJson("/api/events/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('events', ['id' => $id]);
    }

    public function test_events_are_validated(): void
    {
        $this->spa()->postJson('/api/events', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'date']);

        $this->spa()->postJson('/api/events', [
            'title' => 'X',
            'date' => '13.10.2026',
            'time' => '25:00',
            'repeat' => 'day',
            'badge_color' => 'red',
            'badge_icon' => 'skull',
        ])->assertUnprocessable()->assertJsonValidationErrors(['date', 'time', 'repeat', 'badge_color', 'badge_icon']);
    }

    public function test_other_users_events_are_not_found(): void
    {
        $id = $this->spa()->postJson('/api/events', ['title' => 'Mans', 'date' => '2026-10-20'])->json('id');
        $other = User::factory()->create();

        $this->spa($other)->getJson('/api/events')->assertOk()->assertJsonCount(0);
        $this->spa($other)->patchJson("/api/events/{$id}", ['title' => 'Svešs'])->assertNotFound();
        $this->spa($other)->deleteJson("/api/events/{$id}")->assertNotFound();
        $this->assertDatabaseHas('events', ['id' => $id, 'title' => 'Mans']);
    }

    public function test_the_default_event_badge_can_be_set(): void
    {
        $this->spa()->putJson('/api/user/goal-styles', ['event' => ['color' => '#16697A', 'icon' => 'gift']])
            ->assertOk()
            ->assertJsonPath('goal_styles.event.color', '#16697a')
            ->assertJsonPath('goal_styles.event.icon', 'gift');
    }
}
