<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommercialProviderCostBatchLine extends Model
{
    protected $table = 'x_change_commercial_provider_cost_batch_lines';

    protected $fillable = ['batch_id', 'commercial_allocation_id', 'settlement_id', 'expected_amount_minor'];

    protected function casts(): array
    {
        return ['expected_amount_minor' => 'integer'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CommercialProviderCostBatch::class, 'batch_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(CommercialAllocation::class, 'commercial_allocation_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CommercialProviderCostSettlement::class, 'settlement_id');
    }
}
