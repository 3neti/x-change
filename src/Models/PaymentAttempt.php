<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;

class PaymentAttempt extends Model
{
    protected $table = 'x_change_payment_attempts';

    protected $fillable = [
        'reference',
        'voucher_id',
        'provider_code',
        'expected_amount_minor',
        'currency',
        'status',
        'version',
        'session_key_hash',
        'idempotency_key_hash',
        'idempotency_fingerprint',
        'provider_reference_hash',
        'provider_request_id_ciphertext',
        'funding_address_ciphertext',
        'funding_address_hash',
        'instructions_ciphertext',
        'destination_snapshot_ciphertext',
        'destination_fingerprint',
        'matched_observation_id',
        'voucher_collection_id',
        'provider_transaction_id',
        'instructions_created_at',
        'last_checked_at',
        'verified_at',
        'settled_at',
        'expired_at',
        'expires_at',
        'metadata',
    ];

    protected $hidden = [
        'session_key_hash',
        'idempotency_key_hash',
        'idempotency_fingerprint',
        'provider_reference_hash',
        'provider_request_id_ciphertext',
        'funding_address_ciphertext',
        'funding_address_hash',
        'instructions_ciphertext',
        'destination_snapshot_ciphertext',
        'destination_fingerprint',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $attempt): void {
            $attempt->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new \LogicException('Payment Attempts must be changed through guarded payment actions.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Payment Attempts cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'expected_amount_minor' => 'integer',
            'status' => PaymentAttemptStatus::class,
            'version' => 'integer',
            'provider_request_id_ciphertext' => 'encrypted',
            'funding_address_ciphertext' => 'encrypted',
            'instructions_ciphertext' => 'encrypted:array',
            'destination_snapshot_ciphertext' => 'encrypted:array',
            'matched_observation_id' => 'integer',
            'voucher_collection_id' => 'integer',
            'instructions_created_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentAttemptEvent::class)->orderBy('sequence');
    }
}
