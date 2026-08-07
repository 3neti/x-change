<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PartnerCommissionPayoutBatchLine extends Model
{
    protected $table = 'x_change_partner_commission_payout_batch_lines';

    protected $fillable = ['batch_id', 'commercial_allocation_id', 'amount_minor'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PartnerCommissionPayoutBatch::class, 'batch_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(CommercialAllocation::class, 'commercial_allocation_id');
    }
}
