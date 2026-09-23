<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LBHurtado\XCampaign\Models\EndpointCampaign;

final class CampaignPaymentSource extends Model
{
    protected $table = 'x_change_campaign_payment_sources';

    protected $fillable = [
        'reference',
        'endpoint_campaign_id',
        'voucher_collection_id',
        'payment_attempt_id',
        'source_kind',
        'campaign_revision_id',
        'provider_code',
        'currency',
        'configuration_hash',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $source): void {
            $source->reference ??= (string) Str::ulid();
        });
        self::updating(fn (): never => throw new \LogicException('Campaign payment sources are immutable.'));
        self::deleting(fn (): never => throw new \LogicException('Campaign payment sources cannot be deleted.'));
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EndpointCampaign::class, 'endpoint_campaign_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(VoucherCollection::class, 'voucher_collection_id');
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function recognition(): HasOne
    {
        return $this->hasOne(CampaignPaymentRecognition::class, 'campaign_payment_source_id');
    }
}
