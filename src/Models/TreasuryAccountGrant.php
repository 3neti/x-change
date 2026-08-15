<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\TreasuryAccountGrantStatus;

final class TreasuryAccountGrant extends Model
{
    protected $table = 'x_change_treasury_account_grants';

    protected $fillable = [
        'reference', 'status', 'recipient_type', 'recipient_id', 'amount_minor',
        'currency', 'connection_reference', 'purpose', 'test_allocation',
        'request_hash', 'idempotency_reference_hash', 'maker_type', 'maker_id',
        'submitted_at', 'checker_type', 'checker_id', 'approved_at', 'rejected_at',
        'rejection_reason', 'source_position_reference', 'destination_position_reference',
        'operation_reference', 'executed_at',
    ];

    protected $attributes = [
        'status' => 'awaiting_approval',
        'currency' => 'PHP',
        'test_allocation' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => TreasuryAccountGrantStatus::class,
            'amount_minor' => 'integer',
            'test_allocation' => 'boolean',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
        ];
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function maker(): MorphTo
    {
        return $this->morphTo();
    }

    public function checker(): MorphTo
    {
        return $this->morphTo();
    }
}
