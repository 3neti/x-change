<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

final class StoredValueCollection extends Model
{
    protected $table = 'x_change_stored_value_collections';

    protected $fillable = [
        'reference', 'stored_value_holder_binding_id', 'collector_channel',
        'collector_type', 'collector_id', 'collector_session_hash',
        'idempotency_key_hash', 'request_hash', 'amount_minor', 'currency',
        'status', 'provider_reference_ciphertext', 'provider_reference_hash',
        'holder_mobile_hash', 'proof_reference_hash', 'attempts', 'expires_at',
        'authorization_method',
        'verified_at', 'committed_at', 'balance_after_minor',
        'treasury_operation_reference_hash', 'receipt',
    ];

    protected $hidden = [
        'provider_reference_ciphertext', 'provider_reference_hash',
        'holder_mobile_hash', 'proof_reference_hash', 'idempotency_key_hash',
        'request_hash', 'collector_session_hash', 'treasury_operation_reference_hash',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $collection): void {
            $collection->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'attempts' => 'integer',
            'provider_reference_ciphertext' => 'encrypted',
            'receipt' => 'array',
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'committed_at' => 'immutable_datetime',
        ];
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(StoredValueHolderBinding::class, 'stored_value_holder_binding_id');
    }

    public function collector(): MorphTo
    {
        return $this->morphTo('collector', 'collector_type', 'collector_id');
    }
}
