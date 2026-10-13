<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A user's events. The list is small (birthdays, anniversaries, appointments), so it is
 * loaded whole; the frontend works out each event's next occurrence and countdown.
 */
class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->events()->orderBy('date')->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $event = $request->user()->events()->create($this->validated($request));

        return response()->json($event, 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorizeOwner($request, $event);

        $event->update($this->validated($request, partial: true));

        return response()->json($event);
    }

    public function destroy(Request $request, Event $event): Response
    {
        $this->authorizeOwner($request, $event);

        $event->delete();

        return response()->noContent();
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'title' => [$required, 'string', 'max:120'],
            'date' => [$required, 'date_format:Y-m-d'],
            'time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'repeat' => ['sometimes', 'nullable', Rule::in(Event::REPEATS)],
            'badge_color' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'badge_icon' => ['sometimes', 'nullable', Rule::in(config('goals.icons'))],
        ]);

        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
        }
        if (isset($data['date'])) {
            // SQLite stores dates as text: keep the plain Y-m-d string.
            $data['date'] = Carbon::parse($data['date'])->toDateString();
        }
        if (isset($data['badge_color'])) {
            $data['badge_color'] = strtolower($data['badge_color']);
        }

        return $data;
    }

    /**
     * Another user's event is reported as missing, not forbidden.
     */
    private function authorizeOwner(Request $request, Event $event): void
    {
        abort_unless($event->user_id === $request->user()->id, 404);
    }
}
