<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;

class VoucherClaim extends Model
{
    protected $fillable = [
        'voucher_id',
        'claim_number',
        'claim_type',
        'status',
        'requested_amount_minor',
        'disbursed_amount_minor',
        'remaining_balance_minor',
        'currency',
        'claimer_mobile',
        'recipient_country',
        'bank_code',
        'account_number_masked',
        'idempotency_key',
        'reference',
        'attempted_at',
        'completed_at',
        'failure_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'attempted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function getRequestedAmountAttribute(): ?float
    {
        return $this->requested_amount_minor !== null
            ? $this->requested_amount_minor / 100
            : null;
    }

    public function getDisbursedAmountAttribute(): ?float
    {
        return $this->disbursed_amount_minor !== null
            ? $this->disbursed_amount_minor / 100
            : null;
    }

    public function getRemainingBalanceAttribute(): ?float
    {
        return $this->remaining_balance_minor !== null
            ? $this->remaining_balance_minor / 100
            : null;
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPendingReview(): bool
    {
        return $this->status === 'pending_review';
    }
}
