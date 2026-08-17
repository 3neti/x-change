<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;

class AccountFundingReceipt extends Model
{
    protected $table = 'x_change_account_funding_receipts';

    protected $fillable = [
        'reference',
        'standing_funding_address_id',
        'standing_funding_address_binding_revision_id',
        'provider_funding_observation_id',
        'provider_transaction_key',
        'provider_code',
        'account_reference',
        'purpose',
        'recognition_mode_snapshot',
        'status',
        'gross_amount_minor',
        'fee_amount_minor',
        'net_amount_minor',
        'currency',
        'suspense_reason',
        'treasury_inventory_reference',
        'treasury_operation_reference',
        'wallet_transaction_id',
        'wallet_transaction_uuid',
        'observed_at',
        'verified_at',
        'settled_at',
        'suspense_at',
        'reversed_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $receipt): void {
            $receipt->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new \LogicException('Account Funding Receipts must be changed through guarded actions.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Account Funding Receipts cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'purpose' => FundingAddressPurpose::class,
            'recognition_mode_snapshot' => FundingRecognitionMode::class,
            'status' => AccountFundingReceiptStatus::class,
            'gross_amount_minor' => 'integer',
            'fee_amount_minor' => 'integer',
            'net_amount_minor' => 'integer',
            'observed_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'suspense_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function standingFundingAddress(): BelongsTo
    {
        return $this->belongsTo(StandingFundingAddress::class);
    }

    public function providerFundingObservation(): BelongsTo
    {
        return $this->belongsTo(ProviderFundingObservation::class);
    }

    public function standingFundingAddressBindingRevision(): BelongsTo
    {
        return $this->belongsTo(
            StandingFundingAddressBindingRevision::class,
            'standing_funding_address_binding_revision_id',
        );
    }
}
