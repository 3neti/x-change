<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\CommercialProviderCostBatchStatus;

final class CommercialProviderCostBatch extends Model
{
    protected $table = 'x_change_commercial_provider_cost_batches';

    protected $fillable = [
        'reference', 'provider', 'connection_reference', 'currency', 'evidence_type',
        'evidence_reference', 'expected_amount_minor', 'observed_amount_minor',
        'variance_amount_minor', 'status', 'idempotency_key', 'request_hash',
        'recorded_by_type', 'recorded_by_id', 'metadata', 'period_started_at',
        'period_ended_at', 'observed_at', 'settled_at',
    ];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new \LogicException('Provider Cost Batches are append-only.'));
        self::deleting(fn (): never => throw new \LogicException('Provider Cost Batches cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'expected_amount_minor' => 'integer',
            'observed_amount_minor' => 'integer',
            'variance_amount_minor' => 'integer',
            'status' => CommercialProviderCostBatchStatus::class,
            'metadata' => 'array',
            'period_started_at' => 'immutable_datetime',
            'period_ended_at' => 'immutable_datetime',
            'observed_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommercialProviderCostBatchLine::class, 'batch_id');
    }

    public function recordedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
