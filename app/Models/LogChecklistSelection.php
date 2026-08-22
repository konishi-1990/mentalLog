<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['log_id', 'checklist_option_id', 'detail_text', 'duration_min', 'effect_score'])]
class LogChecklistSelection extends Model
{
    protected function casts(): array
    {
        return [
            'duration_min' => 'integer',
            'effect_score' => 'integer',
        ];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(Log::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ChecklistOption::class, 'checklist_option_id');
    }
}
