<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommercialProductRevenueReversal extends Model
{
    protected $table = 'x_change_commercial_product_revenue_reversals';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Product revenue reversals are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Product revenue reversals cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'reversed_at' => 'immutable_datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(CommercialSale::class, 'commercial_sale_id');
    }
}
