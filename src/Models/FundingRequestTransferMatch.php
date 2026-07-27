<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LogicException;

class FundingRequestTransferMatch extends Model
{
    protected $table = 'x_change_funding_request_transfer_matches';

    protected $fillable = [
        'funding_request_id',
        'provider_funding_observation_id',
        'provider_code',
        'connection_reference',
        'amount_minor',
        'currency',
        'status',
        'matched_at',
        'credited_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Funding Request transfer matches must be changed through guarded actions.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Funding Request transfer matches cannot be deleted.',
            );
        });
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'matched_at' => 'immutable_datetime',
            'credited_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function fundingRequest(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class);
    }

    public function providerFundingObservation(): BelongsTo
    {
        return $this->belongsTo(ProviderFundingObservation::class);
    }
}
