<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;

final class PayCodeIssuanceFundingOrder extends Model
{
    protected $table = 'x_change_pay_code_issuance_funding_orders';

    protected $guarded = [];

    protected $hidden = [
        'instructions_ciphertext',
        'pricing_snapshot_ciphertext',
        'idempotency_key_hash',
        'idempotency_fingerprint',
        'amount_lease_active_key',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $order): void {
            $order->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new \LogicException('Issuance Funding Orders must be changed through guarded actions.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Issuance Funding Orders cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'funding_basis' => OnDemandIssuanceFundingBasis::class,
            'status' => PayCodeIssuanceFundingOrderStatus::class,
            'instructions_ciphertext' => 'encrypted:array',
            'pricing_snapshot_ciphertext' => 'encrypted:array',
            'required_amount_minor' => 'integer',
            'reserved_client_funds_minor' => 'integer',
            'on_demand_amount_minor' => 'integer',
            'reconciliation_adjustment_minor' => 'integer',
            'expected_payment_minor' => 'integer',
            'version' => 'integer',
            'payer_acknowledged_at' => 'immutable_datetime',
            'funded_at' => 'immutable_datetime',
            'issuing_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'attention_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'amount_lease_reserved_at' => 'immutable_datetime',
            'amount_lease_reusable_after' => 'immutable_datetime',
            'amount_lease_released_at' => 'immutable_datetime',
            'late_payment_detected_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function fundingIntent(): BelongsTo
    {
        return $this->belongsTo(FundingIntent::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PayCodeIssuanceFundingOrderEvent::class, 'funding_order_id')
            ->orderBy('sequence');
    }
}
