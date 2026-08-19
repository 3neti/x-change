<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;

final class VoucherSliceExecutionOutbox extends Model
{
    protected $table = 'x_change_voucher_slice_execution_outbox';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'reference',
        'execution_id',
        'event_type',
        'event_fingerprint',
        'status',
        'payload',
        'attempts',
        'occurred_at',
        'delivered_at',
        'last_error',
    ];

    protected $hidden = ['event_fingerprint', 'last_error'];

    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'occurred_at' => UtcImmutableDateTime::class,
            'delivered_at' => UtcImmutableDateTime::class,
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(VoucherSliceExecution::class, 'execution_id');
    }
}
