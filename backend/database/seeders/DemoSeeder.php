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
            'locale' => 'lv',
            'goal_styles' => [
                'day' => ['color' => '#4c956c', 'icon' => 'sun'],
                'week' => ['color' => '#489fb5', 'icon' => 'bolt'],
                'month' => ['color' => '#ffc9b9', 'icon' => 'star'],
                'year' => ['color' => '#ffa62b', 'icon' => 'trophy'],
                'custom' => ['color' => '#16697a', 'icon' => 'flag'],
            ],
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $today = Carbon::today();
        $this->habits($user, $today);
        $this->dayGoals($user, $today);
        $this->longerGoals($user, $today);
        $this->boards($user);
    }

    /** Repeating daily goals with ~6 weeks of mostly-kept history (today still open). */
    private function habits(User $user, Carbon $today): void
    {
        $start = $today->copy()->subWeeks(6)->startOfWeek(Carbon::MONDAY);
        $habits = [
            ['Izdzert 2 l ūdens', 'low', '#16697a', 'beaker', null, 0.9],
            ['Lasīt 20 min', 'low', '#d68c45', 'book-open', null, 0.75],
            ['Sporta zāle', 'high', '#ffc9b9', 'bolt', [1, 3, 5], 0.85],
            ['Meditēt 10 min', 'low', null, null, [2, 4, 6, 7], 0.6],
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

        $invoice = $this->goal($user, 'day', $yesterday, 'Nosūtīt rēķinu klientam', 'high', '#489fb5', 'banknotes');
        $this->done($invoice, $yesterday, $yesterday->copy()->setTime(16, 40));
        $this->goal($user, 'day', $yesterday, 'Pierakstīties pie zobārsta', 'important', null, 'heart');

        $run = $this->goal($user, 'day', $today, 'Skriet 5 km', 'important', '#4c956c', 'fire');
        $this->done($run, $today, $today->copy()->setTime(7, 15));
        $this->goal($user, 'day', $today, 'Komandas sapulce', 'high', '#82c0cc', 'user-group', ['due_at' => $today->copy()->setTime(17, 0)]);
        $this->goal($user, 'day', $today, 'Nopirkt dāvanu Annai', 'low', '#d68c45', 'gift');

        $this->goal($user, 'day', $today->copy()->addDay(), 'Iesniegt atskaiti', 'important', '#489fb5', 'briefcase');
        $this->goal($user, 'day', $today->copy()->addDays(2), 'Ģitāras nodarbība', 'low', '#ffc9b9', 'musical-note');
        $this->goal($user, 'day', $today->copy()->addDays(4), 'Cept kūku', 'low', '#ffa62b', 'cake');
        $this->goal($user, 'day', $today->copy()->addDays(6), 'Pārgājiens Siguldā', 'high', '#16697a', 'globe-europe-africa');
    }

    /** Weekly, monthly, yearly and custom-period goals. */
    private function longerGoals(User $user, Carbon $today): void
    {
        $week = $today->copy()->startOfWeek(Carbon::MONDAY);
        $lastWeek = $week->copy()->subWeek();

        $slides = $this->goal($user, 'week', $lastWeek, 'Sagatavot prezentāciju', 'important', '#489fb5', 'light-bulb');
        $this->done($slides, $lastWeek, $lastWeek->copy()->addDays(2)->setTime(18, 5));
        $this->goal($user, 'week', $week, 'Izlasīt 3 nodaļas', 'high', '#d68c45', 'book-open');
        $this->goal($user, 'week', $week, 'Iztīrīt garāžu', 'low', null, 'home');

        $training = $this->goal($user, 'week', $week->copy()->subWeeks(5), '3× treniņš', 'high', '#2c6e49', 'fire', ['repeats' => true]);
        for ($w = $week->copy()->subWeeks(5); $w->lt($week); $w->addWeek()) {
            if ($w->weekOfYear % 4 !== 0) {
                $this->done($training, $w, $w->copy()->addDays(5)->setTime(19, 30));
            }
        }

        $month = $today->copy()->startOfMonth();
        $budget = $this->goal($user, 'month', $month->copy()->subMonths(3), 'Budžeta pārskats', 'high', '#16697a', 'banknotes', ['repeats' => true]);
        for ($m = $month->copy()->subMonths(3); $m->lt($month); $m->addMonth()) {
            $this->done($budget, $m, $m->copy()->addDays(24)->setTime(20, 0));
        }
        $this->goal($user, 'month', $month, 'Iekrāt 300 €', 'high', '#ffa62b', 'banknotes');
        $this->goal($user, 'month', $month, 'Izlasīt 2 grāmatas', 'low', null, 'book-open');

        $year = $today->copy()->startOfYear();
        $this->goal($user, 'year', $year, 'Noskriet pusmaratonu', 'important', '#4c956c', 'trophy');
        $this->goal($user, 'year', $year, 'Iemācīties spāņu valodu', 'high', '#d68c45', 'academic-cap');
        $trip = $this->goal($user, 'year', $year, 'Apceļot 3 jaunas valstis', 'low', '#489fb5', 'paper-airplane');
        $this->done($trip, $year, $today->copy()->subDays(20)->setTime(12, 0));

        $this->goal($user, 'custom', $today->copy()->subDays(5), 'Remonts virtuvē', 'high', '#ffa62b', 'home', ['period_end' => $today->copy()->addDays(5)]);
        $this->goal($user, 'custom', $today->copy()->addDays(10), 'Atvaļinājums Itālijā', 'low', '#d68c45', 'paper-airplane', ['period_end' => $today->copy()->addDays(17)]);
    }

    /** Two boards in Fabric.js scene format (previews are generated the first time they're opened). */
    private function boards(User $user): void
    {
        $note = fn ($text, $x, $y, $bg, $fg = '#000000') => [
            'type' => 'StickyNote', 'version' => '7.4.0', 'text' => $text, 'left' => $x, 'top' => $y, 'width' => 200,
            'fontFamily' => 'Montserrat', 'fontWeight' => 600, 'fontSize' => 20, 'lineHeight' => 1.25,
            'fill' => $fg, 'backgroundColor' => $bg, 'padding' => 14,
        ];
        $text = fn ($value, $x, $y, $size = 30) => [
            'type' => 'IText', 'version' => '7.4.0', 'text' => $value, 'left' => $x, 'top' => $y,
            'fontFamily' => 'Montserrat', 'fontWeight' => 700, 'fontSize' => $size, 'fill' => '#000000',
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
            'title' => 'Nedēļas plāns',
            'data' => ['version' => '7.4.0', 'objects' => [
                $text('Šī nedēļa', 330, 60, 40),
                $note("Pirmdiena\nSporta zāle\nRēķini", 140, 200, '#4c956c'),
                $note("Trešdiena\nPrezentācija\nklientam", 380, 200, '#489fb5', '#ffffff'),
                $note("Piektdiena\nPārgājiens\nSiguldā", 620, 200, '#ffa62b'),
            ], 'viewport' => [1, 0, 0, 1, 0, 0]],
        ]);

        $user->whiteboards()->create([
            'title' => 'Idejas lietotnei',
            'data' => ['version' => '7.4.0', 'objects' => [
                $text('Mērķis → paradums → rezultāts', 380, 60, 28),
                $box(160, 220, 200, 110),
                $text('Mērķis', 160, 220, 26),
                $arrow(270, 220, 390, 220),
                $box(500, 220, 200, 110, '#ffc9b9'),
                $text('Paradums', 500, 220, 26),
                $arrow(610, 220, 730, 220),
                $note("Rezultāts:\nkatru dienu\nnedaudz labāk", 840, 220, '#d68c45'),
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
