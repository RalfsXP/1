<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * A presentable demo account: habits with weeks of history, goals around
 * today, weekly/monthly/yearly/custom goals, custom badges and two boards.
 * Every date is relative to today, so the demo always looks current.
 *
 *   DEMO_PASSWORD='…' php artisan db:seed --class=DemoSeeder --force
 *
 * Re-runnable: it replaces only the demo user (and, by cascade, its data).
 */
class DemoSeeder extends Seeder
{
    public const EMAIL = 'demo@demo.lv';

    public function run(): void
    {
        $password = getenv('DEMO_PASSWORD') ?: env('DEMO_PASSWORD');
        if (! $password) {
            throw new RuntimeException('Set DEMO_PASSWORD to the demo account password.');
        }

        User::where('email', self::EMAIL)->delete();

        $user = User::create([
            'name' => 'Demo',
            'email' => self::EMAIL,
            'password' => $password,
            'locale' => 'en',
            'goal_styles' => [
                'day' => ['color' => '#cbbcff', 'icon' => 'sun'],
                'week' => ['color' => '#c2e1ff', 'icon' => 'bolt'],
                'month' => ['color' => '#ffe27a', 'icon' => 'star'],
                'year' => ['color' => '#bdebc9', 'icon' => 'trophy'],
                'custom' => ['color' => '#ffc9a6', 'icon' => 'flag'],
            ],
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $today = Carbon::today();
        $this->habits($user, $today);
        $this->dayGoals($user, $today);
        $this->longerGoals($user, $today);
        $this->events($user, $today);
        $this->boards($user);
    }

    /** Repeating daily goals with ~6 weeks of mostly-kept history (today still open). */
    private function habits(User $user, Carbon $today): void
    {
        $start = $today->copy()->subWeeks(6)->startOfWeek(Carbon::MONDAY);
        $habits = [
            ['Drink 2 l of water', 'low', '#bdebc9', 'beaker', null, 0.9],
            ['Read 20 minutes', 'low', '#ffc9a6', 'book-open', null, 0.75],
            ['Gym', 'high', '#ffbfd0', 'bolt', [1, 3, 5], 0.85],
            ['Meditate 10 minutes', 'low', null, null, [2, 4, 6, 7], 0.6],
        ];

        foreach ($habits as $i => [$title, $priority, $color, $icon, $days, $rate]) {
            $goal = $this->goal($user, 'day', $start, $title, $priority, $color, $icon, ['repeats' => true, 'repeat_days' => $days]);
            for ($d = $start->copy(); $d->lt($today); $d->addDay()) {
                if ($days && ! in_array($d->dayOfWeekIso, $days, true)) {
                    continue;
                }
                // Deterministic "mostly kept" pattern, a bit better on recent days.
                $kept = (($d->dayOfYear * 7 + $i * 13) % 100) / 100 < ($rate + ($today->diffInDays($d, true) < 7 ? 0.1 : 0));
                if ($kept) {
                    $this->done($goal, $d, $d->copy()->setTime(7 + ($d->day + $i * 3) % 13, ($d->day * 7) % 60));
                }
            }
        }
    }

    /** One-off daily goals around today: done, overdue, due today, coming up. */
    private function dayGoals(User $user, Carbon $today): void
    {
        $yesterday = $today->copy()->subDay();

        $invoice = $this->goal($user, 'day', $yesterday, 'Send the invoice to the client', 'high', '#cbbcff', 'banknotes');
        $this->done($invoice, $yesterday, $yesterday->copy()->setTime(16, 40));
        $this->goal($user, 'day', $yesterday, 'Book a dentist appointment', 'important', null, 'heart');

        $run = $this->goal($user, 'day', $today, 'Run 5 km', 'important', '#cbbcff', 'fire');
        $this->done($run, $today, $today->copy()->setTime(7, 15));
        $this->goal($user, 'day', $today, 'Team meeting', 'high', '#c2e1ff', 'user-group', ['due_at' => $today->copy()->setTime(17, 0)]);
        $this->goal($user, 'day', $today, 'Buy a present for Anna', 'low', '#ffc9a6', 'gift');

        $this->goal($user, 'day', $today->copy()->addDay(), 'Submit the report', 'important', '#cbbcff', 'briefcase');
        $this->goal($user, 'day', $today->copy()->addDays(2), 'Guitar lesson', 'low', '#ffbfd0', 'musical-note');
        $this->goal($user, 'day', $today->copy()->addDays(4), 'Bake a cake', 'low', '#ffe27a', 'cake');
        $this->goal($user, 'day', $today->copy()->addDays(6), 'Hike in Sigulda', 'high', '#bdebc9', 'globe-europe-africa');
    }

    /** Weekly, monthly, yearly and custom-period goals. */
    private function longerGoals(User $user, Carbon $today): void
    {
        $week = $today->copy()->startOfWeek(Carbon::MONDAY);
        $lastWeek = $week->copy()->subWeek();

        $slides = $this->goal($user, 'week', $lastWeek, 'Prepare the presentation', 'important', '#cbbcff', 'light-bulb');
        $this->done($slides, $lastWeek, $lastWeek->copy()->addDays(2)->setTime(18, 5));
        $this->goal($user, 'week', $week, 'Read 3 chapters', 'high', '#ffc9a6', 'book-open');
        $this->goal($user, 'week', $week, 'Clean the garage', 'low', null, 'home');

        $training = $this->goal($user, 'week', $week->copy()->subWeeks(5), 'Work out 3 times', 'high', '#8b74ff', 'fire', ['repeats' => true]);
        for ($w = $week->copy()->subWeeks(5); $w->lt($week); $w->addWeek()) {
            if ($w->weekOfYear % 4 !== 0) {
                $this->done($training, $w, $w->copy()->addDays(5)->setTime(19, 30));
            }
        }

        $month = $today->copy()->startOfMonth();
        $budget = $this->goal($user, 'month', $month->copy()->subMonths(3), 'Budget review', 'high', '#bdebc9', 'banknotes', ['repeats' => true]);
        for ($m = $month->copy()->subMonths(3); $m->lt($month); $m->addMonth()) {
            $this->done($budget, $m, $m->copy()->addDays(24)->setTime(20, 0));
        }
        $this->goal($user, 'month', $month, 'Save €300', 'high', '#ffe27a', 'banknotes');
        $this->goal($user, 'month', $month, 'Read 2 books', 'low', null, 'book-open');

        $year = $today->copy()->startOfYear();
        $this->goal($user, 'year', $year, 'Run a half marathon', 'important', '#cbbcff', 'trophy');
        $this->goal($user, 'year', $year, 'Learn Spanish', 'high', '#ffc9a6', 'academic-cap');
        $trip = $this->goal($user, 'year', $year, 'Visit 3 new countries', 'low', '#cbbcff', 'paper-airplane');
        $this->done($trip, $year, $today->copy()->subDays(20)->setTime(12, 0));

        $this->goal($user, 'custom', $today->copy()->subDays(5), 'Kitchen renovation', 'high', '#ffe27a', 'home', ['period_end' => $today->copy()->addDays(5)]);
        $this->goal($user, 'custom', $today->copy()->addDays(10), 'Holiday in Italy', 'low', '#ffc9a6', 'paper-airplane', ['period_end' => $today->copy()->addDays(17)]);
    }

    /** Birthdays and a few one-off events spread around today, so the countdown list has a mix. */
    private function events(User $user, Carbon $today): void
    {
        $events = [
            ["Anna's birthday", $today->copy()->addDays(27)->subYears(31), null, 'year', '#ffbfd0', 'cake'],
            ["Mum's birthday", $today->copy()->addDays(74)->subYears(58), null, 'year', '#ffe27a', 'gift'],
            ['Wedding anniversary', $today->copy()->addDays(160)->subYears(4), null, 'year', '#ffbfd0', 'heart'],
            ['Rent payment', $today->copy()->startOfMonth()->addDays(14), null, 'month', '#bdebc9', 'banknotes'],
            ['Football with friends', $today->copy()->subWeeks(3)->addDays(2), '19:30', 'week', '#8b74ff', 'user-group'],
            ['Dentist', $today->copy()->addDays(3), '09:15', null, null, null],
            ['Concert at Arena Riga', $today->copy()->addDays(19), '20:00', null, '#cbbcff', 'musical-note'],
            ['Flight to Rome', $today->copy()->addDays(10), '06:40', null, '#c2e1ff', 'paper-airplane'],
        ];

        foreach ($events as [$title, $date, $time, $repeat, $color, $icon]) {
            $user->events()->create([
                'title' => $title,
                'date' => $date->toDateString(),
                'time' => $time,
                'repeat' => $repeat,
                'badge_color' => $color,
                'badge_icon' => $icon,
            ]);
        }
    }

    /** Two boards in Fabric.js scene format (previews are generated the first time they're opened). */
    private function boards(User $user): void
    {
        $note = fn ($text, $x, $y, $bg, $fg = '#000000') => [
            'type' => 'StickyNote', 'version' => '7.4.0', 'text' => $text, 'left' => $x, 'top' => $y, 'width' => 200,
            'fontFamily' => 'DM Sans', 'fontWeight' => 600, 'fontSize' => 20, 'lineHeight' => 1.25,
            'fill' => $fg, 'backgroundColor' => $bg, 'padding' => 14,
        ];
        $text = fn ($value, $x, $y, $size = 30) => [
            'type' => 'IText', 'version' => '7.4.0', 'text' => $value, 'left' => $x, 'top' => $y,
            'fontFamily' => 'DM Sans', 'fontWeight' => 700, 'fontSize' => $size, 'fill' => '#000000',
        ];
        $box = fn ($x, $y, $w, $h, $stroke = '#000000') => [
            'type' => 'Rect', 'version' => '7.4.0', 'left' => $x, 'top' => $y, 'width' => $w, 'height' => $h,
            'rx' => 6, 'ry' => 6, 'fill' => 'transparent', 'stroke' => $stroke, 'strokeWidth' => 5, 'strokeUniform' => true,
        ];
        $arrow = fn ($x1, $y1, $x2, $y2) => [
            'type' => 'Path', 'version' => '7.4.0', 'fill' => '', 'stroke' => '#000000', 'strokeWidth' => 5,
            'strokeLineCap' => 'round', 'strokeLineJoin' => 'round',
            'path' => [['M', $x1, $y1], ['L', $x2, $y2], ['M', $x2 - 18, $y2 - 10], ['L', $x2, $y2], ['L', $x2 - 18, $y2 + 10]],
        ];

        $user->whiteboards()->create([
            'title' => 'Weekly plan',
            'data' => ['version' => '7.4.0', 'objects' => [
                $text('This week', 330, 60, 40),
                $note("Monday\nGym\nInvoices", 140, 200, '#cbbcff'),
                $note("Wednesday\nClient\npresentation", 380, 200, '#c2e1ff'),
                $note("Friday\nHike in\nSigulda", 620, 200, '#ffe27a'),
            ], 'viewport' => [1, 0, 0, 1, 0, 0]],
        ]);

        $user->whiteboards()->create([
            'title' => 'App ideas',
            'data' => ['version' => '7.4.0', 'objects' => [
                $text('Goal → habit → result', 380, 60, 28),
                $box(160, 220, 200, 110),
                $text('Goal', 160, 220, 26),
                $arrow(270, 220, 390, 220),
                $box(500, 220, 200, 110, '#ffbfd0'),
                $text('Habit', 500, 220, 26),
                $arrow(610, 220, 730, 220),
                $note("Result:\na little better\nevery day", 840, 220, '#ffc9a6'),
            ], 'viewport' => [1, 0, 0, 1, 0, 0]],
        ]);
    }

    private function goal(User $user, string $period, Carbon $start, string $title, string $priority, ?string $color, ?string $icon, array $extra = []): Goal
    {
        return $user->goals()->create([
            'period' => $period,
            'period_start' => Goal::normaliseStart($period, $start)->toDateString(),
            'title' => $title,
            'priority' => $priority,
            'badge_color' => $color,
            'badge_icon' => $icon,
            ...array_map(fn ($v) => $v instanceof Carbon ? ($period === 'custom' && $v->isStartOfDay() ? $v->toDateString() : $v) : $v, $extra),
        ]);
    }

    private function done(Goal $goal, Carbon $occursOn, Carbon $at): void
    {
        $goal->completions()->create([
            'occurs_on' => Goal::normaliseStart($goal->period, $occursOn)->toDateString(),
            'completed_at' => $at,
        ]);
    }
}
