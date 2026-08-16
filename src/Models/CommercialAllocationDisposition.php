<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XProvisioning\Enums\CommercialSettlementDisposition;

final class CommercialAllocationDisposition extends Model
{
    protected $table = 'x_change_commercial_allocation_dispositions';

    protected $fillable = [
        'commercial_allocation_id', 'disposition', 'status',
        'designation_reference', 'authority_reference', 'authority_hash',
        'account_reference_hash', 'principal_reference_hash',
        'source_position_reference', 'destination_position_reference',
        'treasury_operation_reference', 'amount_minor', 'currency', 'committed_at',
    ];

    protected $attributes = [
        'status' => 'committed',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Commercial Allocation Dispositions are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Commercial Allocation Dispositions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'disposition' => CommercialSettlementDisposition::class,
            'amount_minor' => 'integer',
            'committed_at' => 'immutable_datetime',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(CommercialAllocation::class, 'commercial_allocation_id');
    }

    public function scopeInternallyCredited(Builder $query): Builder
    {
        return $query->where('disposition', CommercialSettlementDisposition::InternalAccountCredit->value);
    }
}
