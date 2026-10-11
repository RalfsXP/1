<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (POST) or partially update (PATCH) a goal.
 */
class GoalRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'period' => [$required, Rule::in(config('goals.periods'))],
            'period_start' => [$required, 'date'],
            // Order against period_start is checked after normalising (a PATCH may send only one of them).
            'period_end' => ['nullable', 'required_if:period,custom', 'date'],
            'title' => [$required, 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'priority' => ['sometimes', Rule::in(config('goals.priorities'))],
            'due_at' => ['nullable', 'date'],
            'repeats' => ['sometimes', 'boolean'],
            'repeat_days' => ['nullable', 'array'],
            'repeat_days.*' => ['integer', 'between:1,7', 'distinct'],
            'repeat_until' => ['nullable', 'date'],
            'badge_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'badge_icon' => ['nullable', Rule::in(config('goals.icons'))],
        ];
    }
}
