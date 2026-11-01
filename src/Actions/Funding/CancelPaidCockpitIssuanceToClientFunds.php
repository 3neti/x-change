<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryHoldOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldReleaseData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use RuntimeException;

final readonly class CancelPaidCockpitIssuanceToClientFunds
{
    public function __construct(
        private TreasuryHoldOperationContract $holds,
        private TreasuryPrincipalReferenceResolverContract $principals,
        private TransitionPayCodeIssuanceFundingOrder $transition,
    ) {}

    public function handle(PayCodeIssuanceFundingOrder $order, Model $issuer): PayCodeIssuanceFundingOrder
    {
        return DB::transaction(function () use ($order, $issuer): PayCodeIssuanceFundingOrder {
            $locked = PayCodeIssuanceFundingOrder::query()->with('fundingIntent.settlement')
                ->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->issuer_type !== $issuer::class || $locked->issuer_id !== (string) $issuer->getKey()
                || data_get($locked->metadata, 'source') !== 'cockpit.quick-generate') {
                throw new RuntimeException('Only the issuing Account may cancel this cockpit order.');
            }

            if ($locked->status === PayCodeIssuanceFundingOrderStatus::Cancelled
                && $locked->events()->where('event_type', 'funded_order_cancelled_to_client_funds')->exists()) {
                return $locked;
            }

            $settlement = $locked->fundingIntent?->settlement;
            if ($locked->status !== PayCodeIssuanceFundingOrderStatus::IssuanceAttention
                || $locked->voucher_id !== null || $locked->funded_at === null
                || $locked->treasury_hold_reference === null
                || $locked->fundingIntent?->status !== FundingIntentStatus::Settled
                || $settlement === null || $settlement->account_reference !== $locked->account_reference
                || $settlement->currency !== $locked->currency
                || $settlement->net_amount_minor !== $locked->on_demand_amount_minor
                || $locked->reconciliation_adjustment_minor !== 0
                || data_get($locked->metadata, 'issuance_retry.pending') === true
                || data_get($locked->metadata, 'provider_reversal') !== null
                || Checkout::query()->where('funding_order_id', $locked->getKey())->whereHas('refundCase')->exists()) {
                throw new RuntimeException('This paid order is not eligible for cancellation to Client Funds.');
            }

            $hold = TreasuryAllocation::query()->with('releasePosition')->where('allocation_reference', $locked->treasury_hold_reference)
                ->lockForUpdate()->firstOrFail();
            $destination = $hold->releasePosition;
            if ($hold->status !== 'active' || $hold->balance_minor !== $locked->required_amount_minor
                || $hold->currency !== $locked->currency
                || $hold->external_reference !== 'issuance-funding-order:'.$locked->reference
                || $destination === null || $destination->purpose !== TreasuryPositionPurpose::ClientFunds
                || $destination->currency !== $locked->currency
                || $destination->connection_reference !== $locked->connection_reference
                || $destination->principal_reference !== $this->principals->resolve($issuer)) {
                throw new RuntimeException('The order hold cannot be safely released to this Account.');
            }

            $this->holds->release(new TreasuryHoldReleaseData(
                operationReference: 'issuance-hold-cancel-release:'.$locked->reference,
                holdReference: $locked->treasury_hold_reference,
                currency: $locked->currency,
                idempotencyKey: 'issuance-hold-cancel-release-key:'.$locked->reference,
                externalReference: 'issuance-funding-order:'.$locked->reference,
                metadata: ['reason' => 'issuer_cancelled_paid_order'],
            ));

            return $this->transition->handle(
                order: $locked,
                status: PayCodeIssuanceFundingOrderStatus::Cancelled,
                eventType: 'funded_order_cancelled_to_client_funds',
                actorType: $issuer::class,
                actorId: (string) $issuer->getKey(),
                attributes: ['cancelled_at' => now()],
                metadata: ['released_amount_minor' => $hold->balance_minor, 'currency' => $locked->currency],
                approvedFundedCancellation: true,
            );
        }, attempts: 5);
    }
}
