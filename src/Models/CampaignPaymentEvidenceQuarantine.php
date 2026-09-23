<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;

final class CampaignPaymentEvidenceQuarantine extends Model
{
    protected $table = 'x_change_campaign_payment_evidence_quarantines';

    protected $fillable = [
        'reference',
        'quarantine_key',
        'campaign_payment_qr_binding_id',
        'provider_funding_observation_id',
        'provider_code',
        'provider_transaction_key',
        'reason_code',
        'reason_detail',
        'evidence_observation_ids',
        'evidence_fingerprint',
        'opened_at',
    ];

    protected $hidden = [
        'quarantine_key',
        'provider_transaction_key',
        'evidence_observation_ids',
        'evidence_fingerprint',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $quarantine): void {
            $quarantine->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new \LogicException('Campaign payment evidence quarantines are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Campaign payment evidence quarantines cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'evidence_observation_ids' => 'array',
            'opened_at' => 'immutable_datetime',
        ];
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(CampaignPaymentQrBinding::class, 'campaign_payment_qr_binding_id');
    }

    public function triggerObservation(): BelongsTo
    {
        return $this->belongsTo(ProviderFundingObservation::class, 'provider_funding_observation_id');
    }
}
