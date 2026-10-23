<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;

final class ClaimValidationDecision extends Model
{
    protected $table = 'x_change_claim_validation_decisions';

    protected $fillable = [
        'voucher_id',
        'voucher_claim_id',
        'decision_key',
        'instruction_fingerprint',
        'input_fingerprint',
        'status',
        'issues',
        'journal_status',
        'journal_attempts',
        'occurred_at',
        'journaled_at',
        'journal_error',
    ];

    protected function casts(): array
    {
        return [
            'issues' => 'array',
            'journal_attempts' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'journaled_at' => 'immutable_datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(VoucherClaim::class, 'voucher_claim_id');
    }
}
