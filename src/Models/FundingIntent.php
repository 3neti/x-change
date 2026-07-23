<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\FundingIntentStatus;

class FundingIntent extends Model
{
    protected $table = 'x_change_funding_intents';

    protected $fillable = [
        'reference',
        'account_reference',
        'provider_code',
        'expected_amount_minor',
        'currency',
        'status',
        'version',
        'idempotency_key_hash',
        'idempotency_fingerprint',
        'created_by_type',
        'created_by_id',
        'provider_reference',
        'provider_request_id',
        'funding_address_ciphertext',
        'funding_address_hash',
        'instructions_ciphertext',
        'destination_snapshot_ciphertext',
        'destination_fingerprint',
        'matched_observation_id',
        'provider_transaction_id',
        'instructions_created_at',
        'evidence_received_at',
        'verified_at',
        'settled_at',
        'cancelled_at',
        'expired_at',
        'reversed_at',
        'expires_at',
        'metadata',
    ];

    protected $hidden = [
        'idempotency_key_hash',
        'idempotency_fingerprint',
        'funding_address_ciphertext',
        'funding_address_hash',
        'instructions_ciphertext',
        'destination_snapshot_ciphertext',
        'destination_fingerprint',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $intent): void {
            $intent->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new \LogicException('Funding Intents must be changed through guarded funding actions.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Funding Intents cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'expected_amount_minor' => 'integer',
            'status' => FundingIntentStatus::class,
            'version' => 'integer',
            'funding_address_ciphertext' => 'encrypted',
            'instructions_ciphertext' => 'encrypted:array',
            'destination_snapshot_ciphertext' => 'encrypted:array',
            'matched_observation_id' => 'integer',
            'instructions_created_at' => 'immutable_datetime',
            'evidence_received_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(FundingIntentEvent::class)->orderBy('sequence');
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(FundingSettlement::class);
    }

    public function suspenseCases(): HasMany
    {
        return $this->hasMany(FundingSuspenseCase::class);
    }

    public function recovery(): HasOne
    {
        return $this->hasOne(FundingRecovery::class);
    }
}
