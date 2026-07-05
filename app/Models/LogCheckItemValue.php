<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['log_id', 'check_item_id', 'is_on', 'detail_text'])]
class LogCheckItemValue extends Model
{
    protected function casts(): array
    {
        return [
            'is_on' => 'boolean',
        ];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(Log::class);
    }

    public function checkItem(): BelongsTo
    {
        return $this->belongsTo(CheckItem::class);
    }
}
