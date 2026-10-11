<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['occurs_on', 'completed_at'])]
class GoalCompletion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'occurs_on' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }
}
