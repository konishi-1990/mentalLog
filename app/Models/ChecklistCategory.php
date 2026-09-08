<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'sort_order', 'tracks_effect'])]
class ChecklistCategory extends Model
{
    protected function casts(): array
    {
        return [
            'tracks_effect' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(ChecklistOption::class, 'category_id');
    }
}
