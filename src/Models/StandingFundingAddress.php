<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;

class StandingFundingAddress extends Model
{
    protected $table = 'x_change_standing_funding_addresses';

    protected $fillable = [
        'reference',
        'binding_key',
        'owner_type',
        'owner_id',
        'account_reference',
        'provider_code',
        'purpose',
        'recognition_mode',
        'status',
        'version',
        'derivation_scheme',
        'derivation_key_id',
        'derivation_counter',
        'reference_length',
        'provider_reference',
        'funding_address_ciphertext',
        'funding_address_hash',
        'destination_snapshot_ciphertext',
        'destination_fingerprint',
        'currency',
        'minimum_amount_minor',
        'maximum_amount_minor',
        'daily_limit_minor',
        'activated_at',
        'last_qr_issued_at',
        'last_checked_at',
        'suspended_at',
        'retired_at',
        'metadata',
    ];

    protected $hidden = [
        'binding_key',
        'funding_address_ciphertext',
        'funding_address_hash',
        'destination_snapshot_ciphertext',
        'destination_fingerprint',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $address): void {
            $address->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new \LogicException('Standing Funding Addresses must be changed through guarded actions.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Standing Funding Addresses cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'purpose' => FundingAddressPurpose::class,
            'recognition_mode' => FundingRecognitionMode::class,
            'status' => FundingAddressStatus::class,
            'version' => 'integer',
            'derivation_counter' => 'integer',
            'reference_length' => 'integer',
            'funding_address_ciphertext' => 'encrypted',
            'destination_snapshot_ciphertext' => 'encrypted:array',
            'minimum_amount_minor' => 'integer',
            'maximum_amount_minor' => 'integer',
            'daily_limit_minor' => 'integer',
            'activated_at' => 'immutable_datetime',
            'last_qr_issued_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(AccountFundingReceipt::class);
    }
}
