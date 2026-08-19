<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LogicException;

final class StoredValueSpendChallenge extends Model
{
    protected $table = 'x_change_stored_value_spend_challenges';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'reference',
        'partner_api_client_id',
        'stored_value_holder_binding_id',
        'consumed_partner_api_operation_id',
        'idempotency_key_hash',
        'request_hash',
        'mobile_hash',
        'provider',
        'purpose',
        'provider_reference_ciphertext',
        'provider_reference_hash',
        'status',
        'amount_minor',
        'currency',
        'attempts',
        'proof_reference_hash',
        'expires_at',
        'provider_verified_at',
        'verified_at',
        'consumed_at',
    ];

    protected $attributes = [
        'status' => 'delivery_pending',
        'attempts' => 0,
    ];

    protected $hidden = [
        'idempotency_key_hash',
        'request_hash',
        'mobile_hash',
        'provider_reference_ciphertext',
        'provider_reference_hash',
        'proof_reference_hash',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $challenge): void {
            $challenge->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new LogicException('Stored value spend challenges must change through guarded lifecycle actions.');
        });

        self::deleting(function (): never {
            throw new LogicException('Stored value spend challenges cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'provider_reference_ciphertext' => 'encrypted',
            'amount_minor' => 'integer',
            'attempts' => 'integer',
            'expires_at' => UtcImmutableDateTime::class,
            'provider_verified_at' => UtcImmutableDateTime::class,
            'verified_at' => UtcImmutableDateTime::class,
            'consumed_at' => UtcImmutableDateTime::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerApiClient::class, 'partner_api_client_id');
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(StoredValueHolderBinding::class, 'stored_value_holder_binding_id');
    }

    public function consumedOperation(): BelongsTo
    {
        return $this->belongsTo(PartnerApiOperation::class, 'consumed_partner_api_operation_id');
    }
}
