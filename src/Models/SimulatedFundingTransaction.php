<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SimulatedFundingTransaction extends Model
{
    protected $table = 'x_change_simulated_funding_transactions';

    protected $fillable = [
        'reference',
        'provider_request_id',
        'provider_transaction_id',
        'provider_event_id',
        'funding_address',
        'payer_mobile_ciphertext',
        'payer_mobile_hash',
        'gross_amount_minor',
        'fee_amount_minor',
        'currency',
        'status',
        'payload_hash',
        'occurred_at',
        'settled_at',
    ];

    protected $hidden = [
        'payer_mobile_ciphertext',
        'payer_mobile_hash',
    ];

    protected $attributes = [
        'fee_amount_minor' => 0,
        'status' => 'settled',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            $transaction->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new \LogicException('Simulated provider transactions are immutable.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Simulated provider transactions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'payer_mobile_ciphertext' => 'encrypted',
            'gross_amount_minor' => 'integer',
            'fee_amount_minor' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
        ];
    }
}
