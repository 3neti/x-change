<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CampaignDeliveryAttemptEvent extends Model
{
    protected $table = 'x_change_campaign_delivery_attempt_events';

    protected $fillable = [
        'reference',
        'sequence',
        'event_type',
        'provider_status',
        'provider_delivery_reference',
        'safe_error_code',
        'metadata',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new LogicException('Campaign delivery attempt events are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Campaign delivery attempt events are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CampaignDeliveryAttempt::class, 'campaign_delivery_attempt_id');
    }
}
