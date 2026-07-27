<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Enums\FundingTransferAmountReservationStatus;
use LogicException;

class FundingTransferAmountReservation extends Model
{
    protected $table = 'x_change_funding_transfer_amount_reservations';

    protected $fillable = [
        'funding_request_id',
        'provider_code',
        'connection_reference',
        'currency',
        'requested_amount_minor',
        'matching_adjustment_minor',
        'expected_amount_minor',
        'status',
        'active_key',
        'reserved_at',
        'expires_at',
        'reusable_after',
        'matched_at',
        'credited_at',
        'released_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Funding transfer amount reservations must be changed through guarded actions.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Funding transfer amount reservations cannot be deleted.',
            );
        });
    }

    protected function casts(): array
    {
        return [
            'requested_amount_minor' => 'integer',
            'matching_adjustment_minor' => 'integer',
            'expected_amount_minor' => 'integer',
            'status' => FundingTransferAmountReservationStatus::class,
            'reserved_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'reusable_after' => 'immutable_datetime',
            'matched_at' => 'immutable_datetime',
            'credited_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function fundingRequest(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class);
    }
}
