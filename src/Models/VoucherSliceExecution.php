<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LBHurtado\XChange\Enums\VoucherSliceExecutionStatus;

final class VoucherSliceExecution extends Model
{
    protected $table = 'x_change_voucher_slice_executions';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'reference',
        'voucher_id',
        'voucher_claim_id',
        'plan_fingerprint',
        'idempotency_key_hash',
        'request_fingerprint',
        'provider_operation_reference',
        'claim_number',
        'status',
        'amount_minor',
        'currency',
        'version',
        'metadata',
        'reserved_at',
        'executing_at',
        'provider_confirmed_at',
        'settled_at',
        'failed_at',
        'indeterminate_at',
    ];

    protected $hidden = [
        'idempotency_key_hash',
        'request_fingerprint',
        'provider_operation_reference',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'reserved',
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => VoucherSliceExecutionStatus::class,
            'amount_minor' => 'integer',
            'claim_number' => 'integer',
            'version' => 'integer',
            'metadata' => 'array',
            'reserved_at' => UtcImmutableDateTime::class,
            'executing_at' => UtcImmutableDateTime::class,
            'provider_confirmed_at' => UtcImmutableDateTime::class,
            'settled_at' => UtcImmutableDateTime::class,
            'failed_at' => UtcImmutableDateTime::class,
            'indeterminate_at' => UtcImmutableDateTime::class,
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(VoucherClaim::class, 'voucher_claim_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VoucherSliceExecutionItem::class, 'execution_id');
    }

    public function outbox(): HasMany
    {
        return $this->hasMany(VoucherSliceExecutionOutbox::class, 'execution_id');
    }
}
