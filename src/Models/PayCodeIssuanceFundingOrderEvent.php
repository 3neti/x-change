<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;

final class PayCodeIssuanceFundingOrderEvent extends Model
{
    protected $table = 'x_change_pay_code_issuance_funding_order_events';

    protected $guarded = [];

    public $timestamps = false;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Issuance Funding Order events are append-only.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Issuance Funding Order events are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'from_status' => PayCodeIssuanceFundingOrderStatus::class,
            'to_status' => PayCodeIssuanceFundingOrderStatus::class,
            'sequence' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PayCodeIssuanceFundingOrder::class, 'funding_order_id');
    }
}
