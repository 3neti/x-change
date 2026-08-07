<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommercialProviderCostSettlement extends Model
{
    protected $table = 'x_change_commercial_provider_cost_settlements';

    protected $fillable = [
        'commercial_sale_id', 'commercial_allocation_id', 'idempotency_key',
        'request_hash', 'provider', 'connection_reference', 'evidence_type',
        'evidence_reference', 'cash_movement_observed', 'expected_amount_minor',
        'observed_amount_minor', 'variance_amount_minor', 'currency', 'status',
        'position_operation_reference', 'inventory_operation_reference', 'metadata',
        'observed_at', 'settled_at',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Commercial Provider Cost Settlements are append-only.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Commercial Provider Cost Settlements cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'cash_movement_observed' => 'boolean',
            'expected_amount_minor' => 'integer',
            'observed_amount_minor' => 'integer',
            'variance_amount_minor' => 'integer',
            'metadata' => 'array',
            'observed_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(CommercialSale::class, 'commercial_sale_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(CommercialAllocation::class, 'commercial_allocation_id');
    }
}
