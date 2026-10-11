<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Default badge colour + icon for each goal category (a goal can still override it).
 */
class GoalStyleController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $rules = [];
        foreach (config('goals.periods') as $period) {
            $rules[$period] = ['sometimes', 'array'];
            $rules["$period.color"] = ["required_with:$period", 'regex:/^#[0-9a-fA-F]{6}$/'];
            $rules["$period.icon"] = ["required_with:$period", Rule::in(config('goals.icons'))];
        }
        $data = $request->validate($rules);

        $user = $request->user();
        $styles = $user->goal_styles ?? [];
        foreach ($data as $period => $style) {
            $styles[$period] = ['color' => strtolower($style['color']), 'icon' => $style['icon']];
        }
        $user->update(['goal_styles' => $styles]);

        return response()->json($user);
    }
}
