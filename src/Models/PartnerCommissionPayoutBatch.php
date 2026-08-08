<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;

final class PartnerCommissionPayoutBatch extends Model
{
    protected $table = 'x_change_partner_commission_payout_batches';

    protected $fillable = [
        'reference', 'partner_reference', 'commercial_partner_id', 'commercial_partner_revision_id',
        'commercial_partner_destination_revision_id', 'provider', 'connection_reference',
        'position_reference', 'amount_minor', 'currency', 'status', 'destination',
        'destination_hash', 'destination_summary', 'request_idempotency_key',
        'request_hash', 'maker_type', 'maker_id', 'checker_type', 'checker_id',
        'approval_reference', 'submission_idempotency_key', 'provider_transaction_id',
        'provider_transaction_uuid', 'evidence_reference', 'position_operation_reference',
        'inventory_operation_reference', 'metadata', 'period_started_at',
        'period_ended_at', 'requested_at', 'approved_at', 'submitted_at',
        'settled_at', 'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => PartnerCommissionPayoutBatchStatus::class,
            'destination' => 'encrypted:array',
            'metadata' => 'array',
            'period_started_at' => 'immutable_datetime',
            'period_ended_at' => 'immutable_datetime',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
        ];
    }

    protected function destinationSummary(): Attribute
    {
        return Attribute::make(set: static fn (string $value): string => trim($value));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PartnerCommissionPayoutBatchLine::class, 'batch_id');
    }

    public function maker(): MorphTo
    {
        return $this->morphTo();
    }

    public function checker(): MorphTo
    {
        return $this->morphTo();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(CommercialPartner::class, 'commercial_partner_id');
    }

    public function partnerRevision(): BelongsTo
    {
        return $this->belongsTo(CommercialPartnerRevision::class, 'commercial_partner_revision_id');
    }

    public function destinationRevision(): BelongsTo
    {
        return $this->belongsTo(
            CommercialPartnerDestinationRevision::class,
            'commercial_partner_destination_revision_id',
        );
    }
}
