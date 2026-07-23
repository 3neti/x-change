<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingRecoveryPayment extends Model
{
    protected $table = 'x_change_funding_recovery_payments';

    protected $fillable = [
        'funding_recovery_id',
        'funding_settlement_id',
        'amount_minor',
        'currency',
        'wallet_transaction_id',
        'wallet_transaction_uuid',
        'paid_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Funding recovery payments are immutable.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Funding recovery payments cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'paid_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function fundingRecovery(): BelongsTo
    {
        return $this->belongsTo(FundingRecovery::class);
    }

    public function fundingSettlement(): BelongsTo
    {
        return $this->belongsTo(FundingSettlement::class);
    }
}
