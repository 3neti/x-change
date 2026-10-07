<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryHoldOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldPlacementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldReplenishmentData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use RuntimeException;

final readonly class PlaceSettledOnDemandIssuanceHold
{
    public function __construct(
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryHoldOperationContract $holds,
        private ContainOnDemandIssuanceAmountLeaseResidual $residuals,
        private TransitionPayCodeIssuanceFundingOrder $transition,
    ) {}

    public function handle(FundingIntent $intent, int $settledAmountMinor): PayCodeIssuanceFundingOrder
    {
        $order = PayCodeIssuanceFundingOrder::query()
            ->where('funding_intent_id', $intent->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($settledAmountMinor < $order->on_demand_amount_minor) {
            return $this->transition->handle(
                order: $order,
                status: PayCodeIssuanceFundingOrderStatus::Underfunded,
                eventType: 'payment_underfunded',
                actorType: 'funding_settlement_runtime',
                actorId: $intent->provider_code,
                metadata: [
                    'settled_amount_minor' => $settledAmountMinor,
                    'required_amount_minor' => $order->on_demand_amount_minor,
                ],
            );
        }

        $issuer = $this->issuer($order);
        $positions = $this->portfolios->provision(
            $issuer,
            [$order->connection_reference],
        )->positions;
        $clientFunds = collect($positions)->first(
            static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds,
        );
        $payCodeReserve = collect($positions)->first(
            static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::PayCodeReserve,
        );

        if ($clientFunds === null || $payCodeReserve === null) {
            throw new RuntimeException('The Treasury positions required for the settled issuance hold are unavailable.');
        }

        $holdReference = $order->treasury_hold_reference
            ?? 'issuance-hold:'.mb_strtolower($order->reference);

        if ($order->treasury_hold_reference === null) {
            $this->holds->place(new TreasuryHoldPlacementData(
                operationReference: 'issuance-hold-place:'.$order->reference,
                holdReference: $holdReference,
                sourcePositionReference: $clientFunds->positionReference,
                heldPositionReference: $payCodeReserve->positionReference,
                amountMinor: $order->required_amount_minor,
                currency: $order->currency,
                idempotencyKey: 'issuance-hold-place-key:'.$order->reference,
                externalReference: 'issuance-funding-order:'.$order->reference,
                metadata: ['provider_funding_intent' => $intent->reference],
            ));
        } else {
            $this->holds->replenish(new TreasuryHoldReplenishmentData(
                operationReference: 'issuance-hold-replenish:'.$order->reference,
                holdReference: $holdReference,
                sourcePositionReference: $clientFunds->positionReference,
                amountMinor: $order->on_demand_amount_minor,
                currency: $order->currency,
                idempotencyKey: 'issuance-hold-replenish-key:'.$order->reference,
                externalReference: 'issuance-funding-order:'.$order->reference,
                metadata: ['provider_funding_intent' => $intent->reference],
            ));
        }

        $residualHoldReference = $this->residuals->handle(
            order: $order,
            intent: $intent,
            settledAmountMinor: $settledAmountMinor,
            clientFundsPositionReference: $clientFunds->positionReference,
            payCodeReservePositionReference: $payCodeReserve->positionReference,
        );
        $containedAdjustmentMinor = $order->reconciliation_adjustment_minor;

        return $this->transition->handle(
            order: $order,
            status: PayCodeIssuanceFundingOrderStatus::Funded,
            eventType: 'funding_verified_and_held',
            actorType: 'funding_settlement_runtime',
            actorId: $intent->provider_code,
            attributes: [
                'treasury_hold_reference' => $holdReference,
                'funded_at' => now(),
                'amount_lease_active_key' => null,
                'amount_lease_released_at' => now(),
                'metadata' => [
                    ...($order->metadata ?? []),
                    'amount_lease_residual' => [
                        'hold_reference' => $residualHoldReference,
                        'amount_minor' => $containedAdjustmentMinor,
                        'currency' => $order->currency,
                        'status' => $residualHoldReference === null
                            ? 'not_required'
                            : 'contained',
                    ],
                ],
            ],
            metadata: [
                'settled_amount_minor' => $settledAmountMinor,
                'held_amount_minor' => $order->required_amount_minor,
                'contained_amount_lease_adjustment_minor' => $containedAdjustmentMinor,
                'amount_lease_residual_hold_reference' => $residualHoldReference,
                'residual_client_funds_minor' => 0,
                'amount_lease_release_reason' => 'authoritative_payment_matched',
            ],
        );
    }

    private function issuer(PayCodeIssuanceFundingOrder $order): Model
    {
        if (! class_exists($order->issuer_type)
            || ! is_a($order->issuer_type, Model::class, true)) {
            throw new RuntimeException('The issuance funding owner type is unavailable.');
        }

        $issuer = $order->issuer_type::query()->find($order->issuer_id);

        if (! $issuer instanceof Model) {
            throw new RuntimeException('The issuance funding owner is unavailable.');
        }

        return $issuer;
    }
}
