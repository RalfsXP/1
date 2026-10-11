<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A goal for one day/week/month/year, or a recurring series of them.
 *
 * Every occurrence is identified by the first day of its period ("occurs on");
 * a one-off goal has exactly one occurrence, on `period_start`.
 */
#[Fillable(['period', 'period_start', 'period_end', 'title', 'notes', 'priority', 'due_at', 'repeats', 'repeat_days', 'repeat_until', 'badge_color', 'badge_icon'])]
#[Hidden(['completions'])]
class Goal extends Model
{
    protected $appends = ['completed_on', 'completed_at'];

    /**
     * Snap any date to the first day of its period (weeks start on Monday).
     */
    public static function normaliseStart(string $period, mixed $date): Carbon
    {
        $date = Carbon::parse($date)->startOfDay();

        return match ($period) {
            'week' => $date->startOfWeek(Carbon::MONDAY),
            'month' => $date->startOfMonth(),
            'year' => $date->startOfYear(),
            default => $date,
        };
    }

    /**
     * Last day of the period that starts on $start (custom periods: see endOf()).
     */
    public static function periodEnd(string $period, Carbon $start): Carbon
    {
        $start = $start->copy();

        return match ($period) {
            'week' => $start->addDays(6),
            'month' => $start->endOfMonth()->startOfDay(),
            'year' => $start->endOfYear()->startOfDay(),
            default => $start,
        };
    }

    /**
     * Last day of this goal's occurrence starting on $start.
     */
    public function endOf(Carbon $start): Carbon
    {
        return $this->period === 'custom' ? $this->period_end->copy() : self::periodEnd($this->period, $start);
    }

    /**
     * Whether this goal has an occurrence in the period starting on $start.
     */
    public function occursOn(Carbon $start): bool
    {
        if (! $start->equalTo(self::normaliseStart($this->period, $start))) {
            return false;
        }

        if (! $this->repeats) {
            return $start->equalTo($this->period_start);
        }

        if ($start->lt($this->period_start) || ($this->repeat_until && $start->gt($this->repeat_until))) {
            return false;
        }

        return $this->period !== 'day' || empty($this->repeat_days)
            || in_array($start->dayOfWeekIso, $this->repeat_days, true);
    }

    /**
     * Whether any occurrence of this goal falls inside [$from, $to].
     */
    public function overlaps(Carbon $from, Carbon $to): bool
    {
        if ($this->period_start->gt($to)) {
            return false;
        }

        $last = $this->repeats ? $this->repeat_until : $this->period_start;

        return $last === null || $this->endOf($last)->gte($from);
    }

    /**
     * Occurrences ticked off, as "Y-m-d" period starts.
     */
    protected function completedOn(): Attribute
    {
        return Attribute::get(fn () => $this->completions
            ->map(fn (GoalCompletion $c) => $c->occurs_on->toDateString())
            ->values()
            ->all());
    }

    /**
     * When each occurrence was ticked off: {"Y-m-d" occurrence => ISO timestamp}.
     */
    protected function completedAt(): Attribute
    {
        return Attribute::get(fn () => (object) $this->completions
            ->mapWithKeys(fn (GoalCompletion $c) => [$c->occurs_on->toDateString() => $c->completed_at->toJSON()])
            ->all());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(GoalCompletion::class);
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'repeat_until' => 'date:Y-m-d',
            'due_at' => 'datetime',
            'repeats' => 'boolean',
            'repeat_days' => 'array',
        ];
    }
}
