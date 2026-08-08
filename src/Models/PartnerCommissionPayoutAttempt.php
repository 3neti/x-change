<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutAttemptStatus;
use LogicException;

final class PartnerCommissionPayoutAttempt extends Model
{
    protected $table = 'x_change_partner_commission_payout_attempts';

    protected $fillable = [
        'batch_id', 'commercial_partner_destination_revision_id', 'attempt_number', 'status',
        'submission_idempotency_key', 'provider_transaction_id', 'provider_transaction_uuid',
        'evidence_reference', 'rejection_code', 'rejection_message', 'metadata',
        'submitted_at', 'reconciled_at',
    ];

    protected $attributes = [
        'status' => 'submitting',
        'metadata' => '[]',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $attempt): void {
            $mutableOutcomeFields = [
                'status', 'provider_transaction_id', 'provider_transaction_uuid',
                'evidence_reference', 'rejection_code', 'rejection_message',
                'metadata', 'reconciled_at',
            ];

            if (array_diff(array_keys($attempt->getDirty()), $mutableOutcomeFields) !== []) {
                throw new LogicException('Partner Commission payout attempt identity is immutable.');
            }
        });

        self::deleting(function (): never {
            throw new LogicException('Partner Commission payout attempts cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => PartnerCommissionPayoutAttemptStatus::class,
            'metadata' => 'array',
            'submitted_at' => 'immutable_datetime',
            'reconciled_at' => 'immutable_datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PartnerCommissionPayoutBatch::class, 'batch_id');
    }

    public function destinationRevision(): BelongsTo
    {
        return $this->belongsTo(
            CommercialPartnerDestinationRevision::class,
            'commercial_partner_destination_revision_id',
        );
    }
}
