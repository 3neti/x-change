<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final class AssessIssuanceFundingAttribution
{
    public function isUnambiguous(PayCodeIssuanceFundingOrder $order, ProviderFundingObservation $observation): bool
    {
        $query = PayCodeIssuanceFundingOrder::query()
            ->whereKeyNot($order->getKey())
            ->where('provider', $order->provider)
            ->where('currency', $order->currency)
            ->where('expected_payment_minor', $order->expected_payment_minor);

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        $otherOrders = $query->get(['id', 'created_at', 'expires_at', 'amount_lease_reserved_at']);

        if ($otherOrders->isEmpty()) {
            return true;
        }

        $occurredAt = $observation->occurred_at;
        $orderStart = $order->amount_lease_reserved_at ?? $order->created_at;

        if ($occurredAt === null || $orderStart === null || $order->expires_at === null
            || $occurredAt->lessThan($orderStart) || $occurredAt->greaterThan($order->expires_at)) {
            return false;
        }

        return ! $otherOrders->contains(function (PayCodeIssuanceFundingOrder $other) use ($occurredAt): bool {
            $start = $other->amount_lease_reserved_at ?? $other->created_at;

            return $start === null || $other->expires_at === null
                || ($occurredAt->greaterThanOrEqualTo($start) && $occurredAt->lessThanOrEqualTo($other->expires_at));
        });
    }
}
