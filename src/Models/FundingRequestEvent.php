<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LogicException;

class FundingRequestEvent extends Model
{
    protected $table = 'x_change_funding_request_events';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Funding Request events are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Funding Request events are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'from_status' => FundingRequestStatus::class,
            'to_status' => FundingRequestStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function fundingRequest(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class);
    }
}
