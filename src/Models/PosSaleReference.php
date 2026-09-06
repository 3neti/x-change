<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\Voucher\Models\Voucher;
use LogicException;

class PosSaleReference extends Model
{
    protected $table = 'x_change_pos_sale_references';

    protected $fillable = [
        'voucher_id',
        'sale_reference',
        'order_reference',
        'purpose',
        'operator_type',
        'operator_id',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('POS sale references are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('POS sale references cannot be deleted.');
        });
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function operator(): MorphTo
    {
        return $this->morphTo();
    }
}
