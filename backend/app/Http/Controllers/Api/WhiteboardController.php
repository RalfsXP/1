<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Whiteboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class WhiteboardController extends Controller
{
    /** Largest scene we store (JSON bytes) — plenty for hand drawing, guards the free DB. */
    private const MAX_DATA_BYTES = 2 * 1024 * 1024;

    private const MAX_PREVIEW_BYTES = 200 * 1024;

    /**
     * The board list: no scene data, just what the cards show.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->whiteboards()
                ->select(['id', 'title', 'preview', 'updated_at'])
                ->latest('updated_at')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => ['nullable', 'string', 'max:120']]);

        $board = $request->user()->whiteboards()->create([
            'title' => trim($data['title'] ?? '') ?: __('whiteboard.untitled'),
        ]);

        return response()->json($board, 201);
    }

    public function show(Request $request, Whiteboard $whiteboard): JsonResponse
    {
        $this->authorizeOwner($request, $whiteboard);

        return response()->json($whiteboard);
    }

    public function update(Request $request, Whiteboard $whiteboard): JsonResponse
    {
        $this->authorizeOwner($request, $whiteboard);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'data' => ['sometimes', 'nullable', 'array'],
            'preview' => ['sometimes', 'nullable', 'string', 'starts_with:data:image/'],
        ]);

        if (isset($data['data']) && strlen(json_encode($data['data'])) > self::MAX_DATA_BYTES) {
            throw ValidationException::withMessages(['data' => __('whiteboard.too_large')]);
        }
        if (isset($data['preview']) && strlen($data['preview']) > self::MAX_PREVIEW_BYTES) {
            throw ValidationException::withMessages(['preview' => __('whiteboard.too_large')]);
        }

        $whiteboard->update($data);

        // The editor only needs to know the save landed; skip echoing the whole scene back.
        return response()->json($whiteboard->only(['id', 'title', 'updated_at']));
    }

    public function destroy(Request $request, Whiteboard $whiteboard): Response
    {
        $this->authorizeOwner($request, $whiteboard);

        $whiteboard->delete();

        return response()->noContent();
    }

    /**
     * Another user's board is reported as missing, not forbidden.
     */
    private function authorizeOwner(Request $request, Whiteboard $whiteboard): void
    {
        abort_unless($whiteboard->user_id === $request->user()->id, 404);
    }
}
