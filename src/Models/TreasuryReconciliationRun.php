<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LBHurtado\XChange\Enums\TreasuryReconciliationRunStatus;

final class TreasuryReconciliationRun extends Model
{
    protected $table = 'x_change_treasury_reconciliation_runs';

    protected $fillable = [
        'reference', 'status', 'connection_reference', 'provider', 'currency', 'purpose',
        'request_hash', 'idempotency_reference_hash', 'maker_type', 'maker_id', 'submitted_at',
        'checker_type', 'checker_id', 'approved_at', 'attempt_count', 'last_attempt_at',
        'provider_balance_minor', 'inventory_balance_minor', 'position_balance_minor',
        'difference_minor', 'evidence_reference', 'observed_at', 'inventory_operation_reference',
        'position_operation_reference', 'reason', 'completed_at', 'failed_at',
    ];

    protected $attributes = [
        'status' => 'awaiting_approval',
        'currency' => 'PHP',
        'attempt_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => TreasuryReconciliationRunStatus::class,
            'attempt_count' => 'integer',
            'provider_balance_minor' => 'integer',
            'inventory_balance_minor' => 'integer',
            'position_balance_minor' => 'integer',
            'difference_minor' => 'integer',
            'submitted_at' => UtcImmutableDateTime::class,
            'approved_at' => UtcImmutableDateTime::class,
            'last_attempt_at' => UtcImmutableDateTime::class,
            'observed_at' => UtcImmutableDateTime::class,
            'completed_at' => UtcImmutableDateTime::class,
            'failed_at' => UtcImmutableDateTime::class,
        ];
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
