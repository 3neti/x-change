<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryHoldOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryHoldPlacementData;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use RuntimeException;

final readonly class ContainOnDemandIssuanceAmountLeaseResidual
{
    public function __construct(
        private TreasuryHoldOperationContract $holds,
    ) {}

    public function handle(
        PayCodeIssuanceFundingOrder $order,
        FundingIntent $intent,
        int $settledAmountMinor,
        string $clientFundsPositionReference,
        string $payCodeReservePositionReference,
    ): ?string {
        if ($settledAmountMinor !== $order->expected_payment_minor) {
            throw new RuntimeException(
                'The settled amount does not match the leased issuance payment amount.',
            );
        }

        $adjustmentMinor = $settledAmountMinor - $order->on_demand_amount_minor;

        if ($adjustmentMinor !== $order->reconciliation_adjustment_minor) {
            throw new RuntimeException(
                'The settled amount lease adjustment does not match the issuance order.',
            );
        }

        if ($adjustmentMinor === 0) {
            return null;
        }

        if ($adjustmentMinor < 0) {
            throw new RuntimeException('The settled amount lease adjustment cannot be negative.');
        }

        $holdReference = 'issuance-amount-lease-residual:'.mb_strtolower($order->reference);

        $this->holds->place(new TreasuryHoldPlacementData(
            operationReference: 'issuance-amount-lease-residual-place:'.$order->reference,
            holdReference: $holdReference,
            sourcePositionReference: $clientFundsPositionReference,
            heldPositionReference: $payCodeReservePositionReference,
            amountMinor: $adjustmentMinor,
            currency: $order->currency,
            idempotencyKey: 'issuance-amount-lease-residual-place-key:'.$order->reference,
            externalReference: 'issuance-funding-order:'.$order->reference,
            metadata: [
                'provider_funding_intent' => $intent->reference,
                'treasury_hold_kind' => 'amount_lease_residual',
            ],
        ));

        return $holdReference;
    }
}
