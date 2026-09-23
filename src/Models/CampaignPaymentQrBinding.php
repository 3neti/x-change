<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\XCampaign\Models\EndpointCampaign;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Enums\CampaignPaymentAmountMode;

final class CampaignPaymentQrBinding extends Model
{
    protected $table = 'x_change_campaign_payment_qr_bindings';

    protected $fillable = [
        'reference',
        'endpoint_campaign_id',
        'campaign_revision_id',
        'standing_funding_address_id',
        'standing_funding_qr_artifact_id',
        'entry_mode',
        'provider_code',
        'currency',
        'amount_mode',
        'fixed_amount_minor',
        'available_from',
        'available_until',
        'permitted_payment_rules',
        'configuration_hash',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $binding): void {
            $binding->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new \LogicException('Campaign payment QR bindings are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Campaign payment QR bindings cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'entry_mode' => CampaignEntryMode::class,
            'amount_mode' => CampaignPaymentAmountMode::class,
            'fixed_amount_minor' => 'integer',
            'available_from' => 'immutable_datetime',
            'available_until' => 'immutable_datetime',
            'permitted_payment_rules' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EndpointCampaign::class, 'endpoint_campaign_id');
    }

    public function standingFundingAddress(): BelongsTo
    {
        return $this->belongsTo(StandingFundingAddress::class);
    }

    public function qrArtifact(): BelongsTo
    {
        return $this->belongsTo(
            StandingFundingQrArtifact::class,
            'standing_funding_qr_artifact_id',
        );
    }
}
