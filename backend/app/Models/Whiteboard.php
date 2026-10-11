<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A freeform board: a Fabric.js scene (drawings, text, notes, shapes) plus a
 * small preview image for the list.
 */
#[Fillable(['title', 'data', 'preview'])]
class Whiteboard extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
