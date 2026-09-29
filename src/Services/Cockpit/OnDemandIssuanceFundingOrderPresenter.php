<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final readonly class OnDemandIssuanceFundingOrderPresenter
{
    public function __construct(
        private FundingInstructionPresenter $instructions,
        private FundingMethodSelectorCockpitReadModel $selectors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(PayCodeIssuanceFundingOrder $order): array
    {
        $order->loadMissing(['fundingIntent', 'voucher']);
        $instructions = $order->fundingIntent === null
            ? []
            : $this->instructions->forIntent($order->fundingIntent);

        return [
            'schema' => 'x-change.cockpit.on-demand-issuance-funding.v1',
            'status' => $order->status->value,
            'funding_required' => $order->on_demand_amount_minor > 0,
            'actions' => [
                'show' => route(
                    'x-change.cockpit.quick-generate.funding-orders.show',
                    ['order' => $order->reference],
                    false,
                ),
                'acknowledge' => route(
                    'x-change.cockpit.quick-generate.funding-orders.acknowledge',
                    ['order' => $order->reference],
                    false,
                ),
                'cancel' => route(
                    'x-change.cockpit.quick-generate.funding-orders.cancel',
                    ['order' => $order->reference],
                    false,
                ),
            ],
            'order' => [
                'reference' => $order->reference,
                'funding_basis' => $order->funding_basis->value,
                'required_amount_minor' => $order->required_amount_minor,
                'reserved_client_funds_minor' => $order->reserved_client_funds_minor,
                'on_demand_amount_minor' => $order->on_demand_amount_minor,
                'reconciliation_adjustment_minor' => $order->reconciliation_adjustment_minor,
                'expected_payment_minor' => $order->expected_payment_minor,
                'currency' => $order->currency,
                'status' => $order->status->value,
                'expires_at' => $order->expires_at?->toIso8601String(),
                'can_cancel' => in_array($order->status->value, [
                    'awaiting_payment',
                    'payer_acknowledged',
                    'underfunded',
                    'payment_ambiguous',
                    'issuance_attention',
                ], true),
                'voucher' => $order->voucher === null
                    ? null
                    : [
                        'code' => $order->voucher->code,
                        'claim_url' => route('x-change.claim.show', ['code' => $order->voucher->code]),
                    ],
            ],
            'funding_selector' => $this->selectors->forOnDemandIssuance(
                orderReference: $order->reference,
                amountMinor: $order->expected_payment_minor,
                currency: $order->currency,
                status: $order->status->value,
                expiresAt: $order->expires_at?->toIso8601String(),
                fundingInstructions: $instructions,
            ),
        ];
    }
}
