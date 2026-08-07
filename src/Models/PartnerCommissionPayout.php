<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PartnerCommissionPayout extends Model
{
    protected $table = 'x_change_partner_commission_payouts';

    protected $fillable = [
        'commercial_sale_id', 'commercial_allocation_id', 'partner_reference',
        'provider', 'connection_reference', 'position_reference', 'amount_minor',
        'currency', 'status', 'request_idempotency_key', 'request_hash',
        'maker_reference', 'checker_reference', 'approval_reference',
        'settlement_idempotency_key', 'settlement_hash', 'evidence_reference',
        'position_operation_reference', 'inventory_operation_reference', 'metadata',
        'requested_at', 'approved_at', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'metadata' => 'array',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
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
