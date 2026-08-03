<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PartnerCommissionPayout extends Model
{
    protected $table = 'x_change_partner_commission_payouts';

    protected $guarded = [];

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
