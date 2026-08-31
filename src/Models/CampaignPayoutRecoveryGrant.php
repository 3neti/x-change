<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;

final class CampaignPayoutRecoveryGrant extends Model
{
    protected $table = 'x_change_campaign_payout_recovery_grants';

    protected $attributes = [
        'status' => 'available',
        'attempts' => 0,
    ];

    protected $fillable = [
        'reference',
        'voucher_id',
        'campaign_worksheet_fulfillment_id',
        'rejected_reconciliation_id',
        'mobile_hash',
        'provider',
        'purpose',
        'provider_challenge_reference_ciphertext',
        'provider_challenge_reference_hash',
        'status',
        'attempts',
        'expires_at',
        'otp_expires_at',
        'provider_verified_at',
        'verified_at',
        'submitting_at',
        'consumed_at',
    ];

    protected $hidden = [
        'mobile_hash',
        'provider_challenge_reference_ciphertext',
        'provider_challenge_reference_hash',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $grant): void {
            $grant->reference ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /** @return BelongsTo<CampaignWorksheetFulfillment, $this> */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(CampaignWorksheetFulfillment::class, 'campaign_worksheet_fulfillment_id');
    }

    /** @return BelongsTo<DisbursementReconciliation, $this> */
    public function rejection(): BelongsTo
    {
        return $this->belongsTo(DisbursementReconciliation::class, 'rejected_reconciliation_id');
    }

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'provider_challenge_reference_ciphertext' => 'encrypted',
            'expires_at' => UtcImmutableDateTime::class,
            'otp_expires_at' => UtcImmutableDateTime::class,
            'provider_verified_at' => UtcImmutableDateTime::class,
            'verified_at' => UtcImmutableDateTime::class,
            'submitting_at' => UtcImmutableDateTime::class,
            'consumed_at' => UtcImmutableDateTime::class,
        ];
    }
}
