<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;

class VoucherCollection extends Model
{
    protected $guarded = [];

    protected $casts = [
        'requested_amount_minor' => 'integer',
        'collected_amount_minor' => 'integer',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function requestedAmount(): float
    {
        return $this->requested_amount_minor / 100;
    }

    public function collectedAmount(): float
    {
        return $this->collected_amount_minor / 100;
    }

    public function isSucceeded(): bool
    {
        return in_array($this->status, ['collected', 'succeeded'], true);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
