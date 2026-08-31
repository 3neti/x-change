<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LogicException;

class CampaignDeliveryAttempt extends Model
{
    /** @var list<string> */
    public const TerminalEventTypes = ['completed', 'failed', 'superseded'];

    protected $table = 'x_change_campaign_delivery_attempts';

    protected $fillable = [
        'reference',
        'campaign_worksheet_authorization_id',
        'campaign_worksheet_fulfillment_id',
        'channel',
        'attempt_number',
        'idempotency_key_hash',
        'retry_of_reference',
        'requested_by_type',
        'requested_by_id',
        'recipient_route_hash',
        'metadata',
        'requested_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $attempt): void {
            $attempt->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new LogicException('Campaign delivery attempts are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Campaign delivery attempts cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'metadata' => 'array',
            'requested_at' => 'immutable_datetime',
        ];
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(CampaignWorksheetAuthorization::class, 'campaign_worksheet_authorization_id');
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(CampaignWorksheetFulfillment::class, 'campaign_worksheet_fulfillment_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CampaignDeliveryAttemptEvent::class)->orderBy('sequence');
    }
}
