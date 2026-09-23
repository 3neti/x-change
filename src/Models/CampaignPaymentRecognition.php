<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;

final class CampaignPaymentRecognition extends Model
{
    protected $table = 'x_change_campaign_payment_recognitions';

    protected $fillable = [
        'reference',
        'recognition_key',
        'campaign_payment_qr_binding_id',
        'canonical_provider_funding_observation_id',
        'campaign_revision_id',
        'provider_code',
        'provider_transaction_key',
        'provider_operation_key',
        'request_key',
        'gross_amount_minor',
        'fee_amount_minor',
        'net_amount_minor',
        'currency',
        'provider_status',
        'settlement_rail',
        'destination_verified',
        'occurred_at',
        'settled_at',
        'evidence_observation_ids',
        'evidence_fingerprint',
        'binding_configuration_hash',
        'rule_snapshot',
        'recognized_at',
    ];

    protected $hidden = [
        'recognition_key',
        'provider_transaction_key',
        'provider_operation_key',
        'request_key',
        'evidence_observation_ids',
        'evidence_fingerprint',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $recognition): void {
            $recognition->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new \LogicException('Campaign payment recognitions are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Campaign payment recognitions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'gross_amount_minor' => 'integer',
            'fee_amount_minor' => 'integer',
            'net_amount_minor' => 'integer',
            'destination_verified' => 'boolean',
            'occurred_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'evidence_observation_ids' => 'array',
            'rule_snapshot' => 'array',
            'recognized_at' => 'immutable_datetime',
        ];
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(CampaignPaymentQrBinding::class, 'campaign_payment_qr_binding_id');
    }

    public function canonicalObservation(): BelongsTo
    {
        return $this->belongsTo(
            ProviderFundingObservation::class,
            'canonical_provider_funding_observation_id',
        );
    }

    public function provisionalCoverage(): HasOne
    {
        return $this->hasOne(ProvisionalCoverage::class, 'campaign_payment_recognition_id');
    }
}
