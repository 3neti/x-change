<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use RuntimeException;

final class ReserveOnDemandIssuanceAmountLease
{
    public function handle(PayCodeIssuanceFundingOrder $order): PayCodeIssuanceFundingOrder
    {
        $lockName = 'x-change:on-demand-amount-lease:'.hash('sha256', implode('|', [
            $order->provider,
            $order->connection_reference,
            $order->currency,
        ]));
        $lockSeconds = (int) config('x-change.issuance_funding.on_demand.amount_lease.lock_seconds', 15);
        $waitSeconds = (int) config('x-change.issuance_funding.on_demand.amount_lease.lock_wait_seconds', 5);

        return Cache::lock($lockName, $lockSeconds)->block(
            $waitSeconds,
            fn (): PayCodeIssuanceFundingOrder => DB::transaction(
                fn (): PayCodeIssuanceFundingOrder => $this->reserve($order),
                attempts: 5,
            ),
        );
    }

    private function reserve(PayCodeIssuanceFundingOrder $order): PayCodeIssuanceFundingOrder
    {
        $locked = PayCodeIssuanceFundingOrder::query()->lockForUpdate()->findOrFail($order->getKey());

        if ($locked->amount_lease_active_key !== null) {
            return $locked->refresh()->load(['fundingIntent', 'events']);
        }

        $maximumAdjustmentMinor = (int) config(
            'x-change.issuance_funding.on_demand.amount_lease.maximum_adjustment_minor',
            99,
        );

        for ($adjustmentMinor = 0; $adjustmentMinor <= $maximumAdjustmentMinor; $adjustmentMinor++) {
            $expectedPaymentMinor = $locked->on_demand_amount_minor + $adjustmentMinor;
            $activeKey = $this->activeKey($locked, $expectedPaymentMinor);

            if (PayCodeIssuanceFundingOrder::query()
                ->where('amount_lease_active_key', $activeKey)
                ->exists()) {
                continue;
            }

            $locked->forceFill([
                'reconciliation_adjustment_minor' => $adjustmentMinor,
                'expected_payment_minor' => $expectedPaymentMinor,
                'amount_lease_active_key' => $activeKey,
                'amount_lease_reserved_at' => now(),
                'amount_lease_reusable_after' => $locked->expires_at->addSeconds(
                    (int) config(
                        'x-change.issuance_funding.on_demand.amount_lease.reuse_delay_seconds',
                        3600,
                    ),
                ),
                'version' => $locked->version + 1,
            ])->saveQuietly();
            $locked->events()->create([
                'sequence' => $locked->version,
                'event_type' => 'transfer_amount_leased',
                'from_status' => $locked->status,
                'to_status' => $locked->status,
                'actor_type' => self::class,
                'actor_id' => $locked->reference,
                'metadata' => [
                    'base_amount_minor' => $locked->on_demand_amount_minor,
                    'adjustment_minor' => $adjustmentMinor,
                    'expected_payment_minor' => $expectedPaymentMinor,
                ],
                'occurred_at' => now(),
            ]);

            return $locked->refresh()->load(['fundingIntent', 'events']);
        }

        throw new RuntimeException('No collision-safe transfer amount is currently available.');
    }

    private function activeKey(PayCodeIssuanceFundingOrder $order, int $expectedPaymentMinor): string
    {
        return hash('sha256', implode('|', [
            $order->provider,
            $order->connection_reference,
            $order->currency,
            $expectedPaymentMinor,
        ]));
    }
}
