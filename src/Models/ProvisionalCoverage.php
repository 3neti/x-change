<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\XCampaign\Models\EndpointCampaign;
use LBHurtado\XChange\Enums\ProvisionalCoverageStatus;

final class ProvisionalCoverage extends Model
{
    protected $table = 'x_change_provisional_coverages';

    protected $fillable = [
        'reference',
        'coverage_key',
        'campaign_payment_recognition_id',
        'envelope_id',
        'endpoint_campaign_id',
        'campaign_payment_qr_binding_id',
        'campaign_revision_id',
        'driver_id',
        'driver_version',
        'status',
        'coverage_type',
        'coverage_amount_minor',
        'currency',
        'effective_at',
        'expires_at',
        'payment_snapshot',
        'terms_snapshot',
        'authorization_snapshot',
        'payment_snapshot_hash',
        'terms_snapshot_hash',
        'authorization_snapshot_hash',
        'snapshot_hash',
        'bound_at',
    ];

    protected $hidden = [
        'payment_snapshot',
        'terms_snapshot',
        'authorization_snapshot',
        'snapshot_hash',
        'coverage_key',
        'payment_snapshot_hash',
        'terms_snapshot_hash',
        'authorization_snapshot_hash',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $coverage): void {
            $coverage->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new \LogicException('Provisional coverage records are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Provisional coverage records cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ProvisionalCoverageStatus::class,
            'coverage_amount_minor' => 'integer',
            'effective_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'payment_snapshot' => 'array',
            'terms_snapshot' => 'array',
            'authorization_snapshot' => 'array',
            'bound_at' => 'immutable_datetime',
        ];
    }

    public function recognition(): BelongsTo
    {
        return $this->belongsTo(
            CampaignPaymentRecognition::class,
            'campaign_payment_recognition_id',
        );
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EndpointCampaign::class, 'endpoint_campaign_id');
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(
            CampaignPaymentQrBinding::class,
            'campaign_payment_qr_binding_id',
        );
    }

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class);
    }
}
