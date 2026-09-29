<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryHoldOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldReleaseData;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final readonly class ExpireOnDemandIssuanceFundingOrder
{
    public function __construct(
        private TreasuryHoldOperationContract $holds,
        private TransitionPayCodeIssuanceFundingOrder $transition,
    ) {}

    public function handle(PayCodeIssuanceFundingOrder $order): PayCodeIssuanceFundingOrder
    {
        return DB::transaction(function () use ($order): PayCodeIssuanceFundingOrder {
            $locked = PayCodeIssuanceFundingOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($locked->expires_at->isFuture() || ! in_array($locked->status, [
                PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
                PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
                PayCodeIssuanceFundingOrderStatus::Underfunded,
                PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
                PayCodeIssuanceFundingOrderStatus::IssuanceAttention,
            ], true)) {
                return $locked->refresh()->load(['fundingIntent', 'events']);
            }

            if ($locked->treasury_hold_reference !== null) {
                $this->holds->release(new TreasuryHoldReleaseData(
                    operationReference: 'issuance-hold-release:'.$locked->reference,
                    holdReference: $locked->treasury_hold_reference,
                    currency: $locked->currency,
                    idempotencyKey: 'issuance-hold-release-key:'.$locked->reference,
                    externalReference: 'issuance-funding-order:'.$locked->reference,
                    metadata: ['reason' => 'funding_order_expired'],
                ));
            }

            return $this->transition->handle(
                order: $locked,
                status: PayCodeIssuanceFundingOrderStatus::Expired,
                eventType: 'funding_order_expired',
                actorType: self::class,
                actorId: $locked->reference,
                attributes: ['expired_at' => now()],
                metadata: [
                    'late_payment_disposition' => config(
                        'x-change.issuance_funding.on_demand.late_payment_disposition',
                        'client_funds',
                    ),
                    'amount_lease_reusable_after' => $locked->amount_lease_reusable_after?->toIso8601String(),
                ],
            );
        }, attempts: 5);
    }
}
