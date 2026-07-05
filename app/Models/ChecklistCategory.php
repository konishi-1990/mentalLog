<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'sort_order'])]
class ChecklistCategory extends Model
{
    public function options(): HasMany
    {
        return $this->hasMany(ChecklistOption::class, 'category_id');
    }
}
