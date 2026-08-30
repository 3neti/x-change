<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LogicException;

final class CampaignBatchFulfillmentOutbox extends Model
{
    protected $table = 'x_change_campaign_batch_fulfillment_outbox';

    protected $fillable = [
        'campaign_worksheet_authorization_id',
        'status',
        'attempts',
        'available_at',
        'locked_at',
        'completed_at',
        'last_error_class',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $outbox): void {
            $outbox->reference ??= (string) Str::ulid();
        });
        self::updating(function (): never {
            throw new LogicException('Campaign batch outbox records must be mutated by their processor.');
        });
        self::deleting(function (): never {
            throw new LogicException('Campaign batch outbox records are append-only.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<CampaignWorksheetAuthorization, $this> */
    public function authorization(): BelongsTo
    {
        return $this->belongsTo(CampaignWorksheetAuthorization::class, 'campaign_worksheet_authorization_id');
    }
}
