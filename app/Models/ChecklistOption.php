<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'label', 'requires_text', 'is_none', 'sort_order', 'is_active'])]
class ChecklistOption extends Model
{
    protected function casts(): array
    {
        return [
            'requires_text' => 'boolean',
            'is_none' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ChecklistCategory::class, 'category_id');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(LogChecklistSelection::class);
    }
}
