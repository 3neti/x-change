<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LBHurtado\XChange\Enums\CampaignPaymentMonitoringMode;

final class CampaignPaymentMonitoringControl extends Model
{
    protected $table = 'x_change_campaign_payment_monitoring_controls';

    protected $guarded = [];

    protected $attributes = [
        'mode' => 'paused',
        'generation' => 0,
    ];

    protected function casts(): array
    {
        return [
            'mode' => CampaignPaymentMonitoringMode::class,
            'generation' => 'integer',
            'transitioned_at' => UtcImmutableDateTime::class,
        ];
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(CampaignPaymentQrBinding::class, 'campaign_payment_qr_binding_id');
    }
}
