<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Enums\FundingIntentStatus;

class FundingIntentEvent extends Model
{
    protected $table = 'x_change_funding_intent_events';

    protected $fillable = [
        'sequence',
        'event_type',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'evidence_reference',
        'metadata',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Funding Intent events are append-only.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Funding Intent events are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'from_status' => FundingIntentStatus::class,
            'to_status' => FundingIntentStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function fundingIntent(): BelongsTo
    {
        return $this->belongsTo(FundingIntent::class);
    }
}
