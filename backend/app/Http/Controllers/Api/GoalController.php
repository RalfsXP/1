<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoalRequest;
use App\Models\Goal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class GoalController extends Controller
{
    /**
     * Goals with an occurrence inside [from, to], with the occurrences already completed.
     * The client expands recurring goals into their individual occurrences.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay();
        // A year is the longest fixed period, so nothing starting before this year can reach $from —
        // except a week that began in December (custom periods are matched by their end instead).
        $earliest = $from->copy()->startOfYear()->min($from->copy()->startOfWeek(Carbon::MONDAY));

        // Compare as plain dates: SQLite (tests) stores them as text.
        [$fromDay, $toDay, $earliestDay] = [$from->toDateString(), $to->toDateString(), $earliest->toDateString()];

        $goals = $request->user()->goals()
            ->where('period_start', '<=', $toDay)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->where('repeats', false)->where('period', '!=', 'custom')->where('period_start', '>=', $earliestDay))
                // Custom periods can be any length, so test their end instead.
                ->orWhere(fn (Builder $q) => $q->where('period', 'custom')->where('period_end', '>=', $fromDay))
                ->orWhere(fn (Builder $q) => $q->where('repeats', true)
                    ->where(fn (Builder $q) => $q->whereNull('repeat_until')->orWhere('repeat_until', '>=', $earliestDay))))
            ->orderBy('period_start')
            ->get()
            ->filter(fn (Goal $goal) => $goal->overlaps($from, $to))
            ->values();

        // One-off goals have a single completion whatever its date (a custom period may start long
        // before $from); recurring ones only need the window.
        $oneOffIds = $goals->where('repeats', false)->modelKeys();
        $goals->load(['completions' => fn ($q) => $q
            ->where('occurs_on', '<=', $toDay)
            ->where(fn ($q) => $q->where('occurs_on', '>=', $earliestDay)->orWhereIn('goal_id', $oneOffIds))]);

        return response()->json($goals);
    }

    public function store(GoalRequest $request): JsonResponse
    {
        $goal = $request->user()->goals()->create($this->normalise($request->validated()));

        return response()->json($goal->load('completions'), 201);
    }

    public function update(GoalRequest $request, Goal $goal): JsonResponse
    {
        $this->authorizeOwner($request, $goal);

        $data = $this->normalise([...$goal->only(['period', 'period_start', 'period_end', 'repeats', 'repeat_days', 'repeat_until', 'due_at']), ...$request->validated()]);
        $goal->update($data);

        // Moving or narrowing the goal drops ticks for occurrences that no longer exist.
        $stale = $goal->completions->reject(fn ($c) => $goal->occursOn($c->occurs_on))->modelKeys();
        $goal->completions()->whereKey($stale)->delete();

        return response()->json($goal->load('completions'));
    }

    public function destroy(Request $request, Goal $goal): Response
    {
        $this->authorizeOwner($request, $goal);

        $goal->delete();

        return response()->noContent();
    }

    /**
     * Tick off the occurrence whose period starts on {date}.
     */
    public function complete(Request $request, Goal $goal, string $date): JsonResponse
    {
        $this->authorizeOwner($request, $goal);
        $start = $this->occurrence($goal, $date);

        $goal->completions()->firstOrCreate(
            ['occurs_on' => $start->toDateString()],
            ['completed_at' => now()],
        );

        return response()->json($goal->load('completions'));
    }

    public function uncomplete(Request $request, Goal $goal, string $date): JsonResponse
    {
        $this->authorizeOwner($request, $goal);
        $start = $this->occurrence($goal, $date);

        $goal->completions()->whereDate('occurs_on', $start->toDateString())->delete();

        return response()->json($goal->load('completions'));
    }

    /**
     * Another user's goal is reported as missing, not forbidden.
     */
    private function authorizeOwner(Request $request, Goal $goal): void
    {
        abort_unless($goal->user_id === $request->user()->id, 404);
    }

    private function occurrence(Goal $goal, string $date): Carbon
    {
        try {
            $start = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            $start = null;
        }

        if (! $start || ! $goal->occursOn($start)) {
            throw ValidationException::withMessages(['date' => __('validation.in', ['attribute' => 'date'])]);
        }

        return $start;
    }

    /**
     * Snap dates to period starts and drop settings that don't apply.
     */
    private function normalise(array $data): array
    {
        $period = $data['period'];
        $data['period_start'] = Goal::normaliseStart($period, $data['period_start'])->toDateString();

        // A custom period is a single stretch of days: it has an end and never repeats.
        $custom = $period === 'custom';
        $data['period_end'] = $custom ? Carbon::parse($data['period_end'])->toDateString() : null;
        if ($custom) {
            $data['repeats'] = false;
            if ($data['period_end'] < $data['period_start']) {
                throw ValidationException::withMessages(['period_end' => __('validation.after_or_equal', [
                    'attribute' => __('validation.attributes.period_end'),
                    'date' => $data['period_start'],
                ])]);
            }
        }
        $repeats = (bool) ($data['repeats'] ?? false);

        $days = $data['repeat_days'] ?? null;
        $days = $repeats && $period === 'day' && $days ? array_values(array_unique(array_map('intval', $days))) : [];
        sort($days);
        // No weekdays or all seven both mean "every day".
        $data['repeat_days'] = $days && count($days) < 7 ? $days : null;

        $until = $repeats && ! empty($data['repeat_until'])
            ? Goal::normaliseStart($period, $data['repeat_until'])
            : null;
        if ($until && $until->lt(Carbon::parse($data['period_start']))) {
            throw ValidationException::withMessages(['repeat_until' => __('validation.after_or_equal', [
                'attribute' => __('validation.attributes.repeat_until'),
                'date' => $data['period_start'],
            ])]);
        }
        $data['repeat_until'] = $until?->toDateString();

        // An earlier deadline only makes sense for a single occurrence.
        if ($repeats) {
            $data['due_at'] = null;
        }

        return $data;
    }
}
