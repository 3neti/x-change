<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;

final class VoucherSliceExecutionItem extends Model
{
    protected $table = 'x_change_voucher_slice_execution_items';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'execution_id',
        'voucher_id',
        'slice_id',
        'label',
        'sequence',
        'amount_minor',
        'status',
        'reserved_at',
        'consumed_at',
    ];

    protected $attributes = ['status' => 'reserved'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'amount_minor' => 'integer',
            'reserved_at' => UtcImmutableDateTime::class,
            'consumed_at' => UtcImmutableDateTime::class,
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(VoucherSliceExecution::class, 'execution_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
