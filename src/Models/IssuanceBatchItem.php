<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IssuanceBatchItem extends Model
{
    protected $table = 'x_change_issuance_batch_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_row' => 'integer',
            'ordinal' => 'integer',
            'amount_minor' => 'integer',
            'payload' => 'encrypted:array',
            'issued_at' => 'immutable_datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IssuanceBatch::class, 'batch_id');
    }
}
