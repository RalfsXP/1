<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** Signed-in SPA request (Sanctum session auth needs the SPA origin). */
    private function spa(?User $as = null): static
    {
        return $this->actingAs($as ?? $this->user)->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);
    }

    private function makeGoal(array $attributes = []): Goal
    {
        return $this->user->goals()->create([
            'period' => 'day',
            'period_start' => '2026-10-14',
            'title' => 'Run 5 km',
            ...$attributes,
        ]);
    }

    public function test_goals_require_authentication(): void
    {
        $this->getJson('/api/goals?from=2026-10-01&to=2026-10-31')->assertUnauthorized();
    }

    public function test_a_user_can_create_a_goal_and_its_start_is_snapped_to_the_period(): void
    {
        $this->spa()->postJson('/api/goals', [
            'period' => 'week',
            'period_start' => '2026-10-15', // a Thursday
            'title' => 'Read a book',
            'priority' => 'important',
            'badge_color' => '#FF0066',
            'badge_icon' => 'book-open',
        ])
            ->assertCreated()
            ->assertJsonPath('period_start', '2026-10-12') // that week's Monday
            ->assertJsonPath('priority', 'important')
            ->assertJsonPath('completed_on', []);

        $this->assertDatabaseHas('goals', ['user_id' => $this->user->id, 'title' => 'Read a book']);
    }

    public function test_goal_input_is_validated_in_the_users_language(): void
    {
        $this->spa()->withHeader('Accept-Language', 'lv')->postJson('/api/goals', [
            'period' => 'decade',
            'period_start' => '2026-10-15',
            'title' => '',
            'priority' => 'urgent',
            'badge_color' => 'red',
            'badge_icon' => 'not-an-icon',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period', 'title', 'priority', 'badge_color', 'badge_icon'])
            ->assertJsonPath('errors.title.0', 'Lauks nosaukums ir obligāts.');
    }

    public function test_listing_returns_only_goals_in_range_including_recurring_series(): void
    {
        $this->makeGoal(['title' => 'In range']);
        $this->makeGoal(['title' => 'Too late', 'period_start' => '2026-11-02']);
        $this->makeGoal(['title' => 'Yearly', 'period' => 'year', 'period_start' => '2026-01-01']);
        $this->makeGoal(['title' => 'Daily habit', 'period_start' => '2026-01-05', 'repeats' => true]);
        $this->makeGoal(['title' => 'Stopped habit', 'period_start' => '2026-01-05', 'repeats' => true, 'repeat_until' => '2026-03-01']);
        User::factory()->create()->goals()->create(['period' => 'day', 'period_start' => '2026-10-14', 'title' => 'Not mine']);

        $titles = $this->spa()->getJson('/api/goals?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->json('*.title');

        $this->assertEqualsCanonicalizing(['In range', 'Yearly', 'Daily habit'], $titles);
    }

    public function test_a_one_off_goal_can_be_completed_and_reopened(): void
    {
        $goal = $this->makeGoal();

        $this->travelTo('2026-10-13 18:30:00');
        $this->spa()->putJson("/api/goals/{$goal->id}/completions/2026-10-14")
            ->assertOk()
            ->assertJsonPath('completed_on', ['2026-10-14'])
            ->assertJsonPath('completed_at.2026-10-14', '2026-10-13T18:30:00.000000Z');

        $this->spa()->putJson("/api/goals/{$goal->id}/completions/2026-10-15")
            ->assertUnprocessable();

        $this->spa()->deleteJson("/api/goals/{$goal->id}/completions/2026-10-14")
            ->assertOk()
            ->assertJsonPath('completed_on', []);
    }

    public function test_recurring_daily_goals_are_completed_per_occurrence_on_chosen_weekdays(): void
    {
        $goal = $this->spa()->postJson('/api/goals', [
            'period' => 'day',
            'period_start' => '2026-10-12',
            'title' => 'Gym',
            'repeats' => true,
            'repeat_days' => [5, 1], // Friday, Monday
            'due_at' => '2026-10-12 08:00', // ignored for a series
        ])
            ->assertCreated()
            ->assertJsonPath('repeat_days', [1, 5])
            ->assertJsonPath('due_at', null)
            ->json();

        $this->spa()->putJson("/api/goals/{$goal['id']}/completions/2026-10-16")->assertOk(); // Friday
        $this->spa()->putJson("/api/goals/{$goal['id']}/completions/2026-10-19") // next Monday
            ->assertOk()
            ->assertJsonPath('completed_on', ['2026-10-16', '2026-10-19']);

        // Wednesday isn't one of its days, and nothing happens before the first occurrence.
        $this->spa()->putJson("/api/goals/{$goal['id']}/completions/2026-10-14")->assertUnprocessable();
        $this->spa()->putJson("/api/goals/{$goal['id']}/completions/2026-10-09")->assertUnprocessable();
    }

    public function test_every_weekday_means_every_day(): void
    {
        $this->spa()->postJson('/api/goals', [
            'period' => 'day',
            'period_start' => '2026-10-12',
            'title' => 'Water plants',
            'repeats' => true,
            'repeat_days' => [1, 2, 3, 4, 5, 6, 7],
        ])->assertCreated()->assertJsonPath('repeat_days', null);
    }

    public function test_stopping_a_series_keeps_its_history(): void
    {
        $goal = $this->makeGoal(['period' => 'week', 'period_start' => '2026-10-05', 'repeats' => true]);
        $this->spa()->putJson("/api/goals/{$goal->id}/completions/2026-10-05")->assertOk();
        $this->spa()->putJson("/api/goals/{$goal->id}/completions/2026-10-19")->assertOk();

        $this->spa()->patchJson("/api/goals/{$goal->id}", ['repeat_until' => '2026-10-14'])
            ->assertOk()
            ->assertJsonPath('repeat_until', '2026-10-12')
            ->assertJsonPath('completed_on', ['2026-10-05']);
    }

    public function test_custom_periods_span_their_own_dates_and_never_repeat(): void
    {
        $goal = $this->spa()->postJson('/api/goals', [
            'period' => 'custom',
            'period_start' => '2026-10-13', // Tuesday
            'period_end' => '2026-10-23',   // next Friday
            'title' => 'Write the report',
            'repeats' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('period_start', '2026-10-13')
            ->assertJsonPath('period_end', '2026-10-23')
            ->assertJsonPath('repeats', false)
            ->json();

        // Listed for any range it touches, even one starting mid-period.
        $this->spa()->getJson('/api/goals?from=2026-10-20&to=2026-10-20')->assertJsonCount(1);
        $this->spa()->getJson('/api/goals?from=2026-10-24&to=2026-10-31')->assertJsonCount(0);

        $this->spa()->putJson("/api/goals/{$goal['id']}/completions/2026-10-13")->assertOk();

        $this->spa()->postJson('/api/goals', ['period' => 'custom', 'period_start' => '2026-10-13', 'title' => 'No end'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_end']);
        $this->spa()->withHeader('Accept-Language', 'en')
            ->postJson('/api/goals', ['period' => 'custom', 'period_start' => '2026-10-13', 'period_end' => '2026-10-01', 'title' => 'Backwards'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.period_end.0', 'The end date field must be a date after or equal to 2026-10-13.');
    }

    public function test_listing_keeps_completions_of_long_custom_periods_and_weeks_across_new_year(): void
    {
        $custom = $this->makeGoal(['period' => 'custom', 'period_start' => '2026-12-20', 'period_end' => '2027-01-10']);
        $week = $this->makeGoal(['period' => 'week', 'period_start' => '2026-12-28']);
        $this->spa()->putJson("/api/goals/{$custom->id}/completions/2026-12-20")->assertOk();
        $this->spa()->putJson("/api/goals/{$week->id}/completions/2026-12-28")->assertOk();

        $goals = collect($this->spa()->getJson('/api/goals?from=2027-01-02&to=2027-01-08')->assertOk()->json())->keyBy('id');

        $this->assertSame(['2026-12-20'], $goals[$custom->id]['completed_on']);
        $this->assertSame(['2026-12-28'], $goals[$week->id]['completed_on']);
    }

    public function test_a_user_can_update_and_delete_their_goal(): void
    {
        $goal = $this->makeGoal();

        $this->spa()->patchJson("/api/goals/{$goal->id}", ['title' => 'Run 10 km', 'priority' => 'high'])
            ->assertOk()
            ->assertJsonPath('title', 'Run 10 km')
            ->assertJsonPath('priority', 'high');

        $this->spa()->deleteJson("/api/goals/{$goal->id}")->assertNoContent();
        $this->assertModelMissing($goal);
    }

    public function test_other_users_goals_are_not_found(): void
    {
        $goal = $this->makeGoal();
        $intruder = User::factory()->create();

        $this->spa($intruder)->patchJson("/api/goals/{$goal->id}", ['title' => 'Mine now'])->assertNotFound();
        $this->spa($intruder)->putJson("/api/goals/{$goal->id}/completions/2026-10-14")->assertNotFound();
        $this->spa($intruder)->deleteJson("/api/goals/{$goal->id}")->assertNotFound();
        $this->assertModelExists($goal);
    }

    public function test_a_user_can_set_default_badge_styles_per_category(): void
    {
        $this->spa()->putJson('/api/user/goal-styles', [
            'week' => ['color' => '#AA00FF', 'icon' => 'rocket-launch'],
        ])
            ->assertOk()
            ->assertJsonPath('goal_styles.week', ['color' => '#aa00ff', 'icon' => 'rocket-launch']);

        $this->spa()->putJson('/api/user/goal-styles', [
            'day' => ['color' => 'green', 'icon' => 'sun'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['day.color']);
    }
}
