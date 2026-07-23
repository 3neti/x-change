<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;

class FundingSettlement extends Model
{
    protected $table = 'x_change_funding_settlements';

    protected $fillable = [
        'funding_intent_id',
        'provider_funding_observation_id',
        'provider_code',
        'account_reference',
        'gross_amount_minor',
        'fee_amount_minor',
        'net_amount_minor',
        'currency',
        'treasury_inventory_reference',
        'treasury_operation_reference',
        'wallet_transaction_id',
        'wallet_transaction_uuid',
        'settled_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Funding settlements are immutable.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Funding settlements cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'gross_amount_minor' => 'integer',
            'fee_amount_minor' => 'integer',
            'net_amount_minor' => 'integer',
            'settled_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function fundingIntent(): BelongsTo
    {
        return $this->belongsTo(FundingIntent::class);
    }

    public function providerFundingObservation(): BelongsTo
    {
        return $this->belongsTo(ProviderFundingObservation::class);
    }
}
