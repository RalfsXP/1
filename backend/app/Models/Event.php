<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that happens on a date — once, or every week / month / year (a birthday).
 * Unlike goals there is nothing to complete; the app shows a countdown to the next one.
 */
#[Fillable(['title', 'date', 'time', 'repeat', 'badge_color', 'badge_icon'])]
class Event extends Model
{
    public const REPEATS = ['week', 'month', 'year'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }
}
