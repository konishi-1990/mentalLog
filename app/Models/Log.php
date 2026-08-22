<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'logged_on',
    'stress',
    'stamina',
    'mental_capacity',
    'sleep_hours',
    'sleep_quality',
    'carryover',
    'controllability',
    'day_type',
    'hardest_text',
    'summary_text',
])]
class Log extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'stress' => 'integer',
            'stamina' => 'integer',
            'mental_capacity' => 'integer',
            'sleep_hours' => 'decimal:1',
            'sleep_quality' => 'integer',
            'carryover' => 'integer',
            'controllability' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkItemValues(): HasMany
    {
        return $this->hasMany(LogCheckItemValue::class);
    }

    public function checklistSelections(): HasMany
    {
        return $this->hasMany(LogChecklistSelection::class);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'log_people')
            ->withPivot('detail_text')
            ->withTimestamps();
    }
}
