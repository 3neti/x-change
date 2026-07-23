<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;

class FundingRecovery extends Model
{
    protected $table = 'x_change_funding_recoveries';

    protected $fillable = [
        'reference',
        'funding_intent_id',
        'funding_settlement_id',
        'reversal_observation_id',
        'account_reference',
        'reversal_amount_minor',
        'recovered_amount_minor',
        'outstanding_amount_minor',
        'currency',
        'treasury_reversal_operation_reference',
        'wallet_transaction_id',
        'wallet_transaction_uuid',
        'status',
        'opened_at',
        'recovered_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $recovery): void {
            $recovery->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new \LogicException('Funding recoveries must be changed through guarded recovery actions.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Funding recoveries cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'reversal_amount_minor' => 'integer',
            'recovered_amount_minor' => 'integer',
            'outstanding_amount_minor' => 'integer',
            'opened_at' => 'immutable_datetime',
            'recovered_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function fundingIntent(): BelongsTo
    {
        return $this->belongsTo(FundingIntent::class);
    }

    public function fundingSettlement(): BelongsTo
    {
        return $this->belongsTo(FundingSettlement::class);
    }

    public function reversalObservation(): BelongsTo
    {
        return $this->belongsTo(ProviderFundingObservation::class, 'reversal_observation_id');
    }

    public function accountHold(): HasOne
    {
        return $this->hasOne(FundingAccountHold::class);
    }
}
