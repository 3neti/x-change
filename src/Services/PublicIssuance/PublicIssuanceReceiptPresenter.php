<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final readonly class PublicIssuanceReceiptPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(PayCodeIssuanceFundingOrder $order, bool $canManage = false): array
    {
        $order->loadMissing(['voucher', 'events']);

        return [
            'schema' => 'x-change.public-issuance-receipt.v1',
            'order_reference' => $order->reference,
            'status' => $order->status->value,
            'amount_minor' => $order->expected_payment_minor,
            'currency' => $order->currency,
            'created_at' => $order->created_at?->toIso8601String(),
            'verified_at' => $order->funded_at?->toIso8601String(),
            'issued_at' => $order->issued_at?->toIso8601String(),
            'pay_code' => $order->voucher instanceof Voucher ? [
                'code' => (string) $order->voucher->code,
                'claim_url' => route('x-change.claim.show', [
                    'code' => $order->voucher->code,
                ]),
                'state' => $order->voucher->state?->value,
                'expires_at' => $order->voucher->expires_at?->toIso8601String(),
            ] : null,
            ...($canManage && $order->voucher instanceof Voucher ? ['actions' => [
                'url' => route('x-change.public-auto-generate.issuances.action', ['order' => $order->reference], false),
                'can_extend' => $order->voucher->state?->value === 'active'
                    && $order->voucher->redeemed_at === null
                    && $order->voucher->closed_at === null
                    && $order->voucher->expires_at?->isFuture() === true,
                'can_cancel' => $order->voucher->state?->value === 'active'
                    && $order->voucher->redeemed_at === null
                    && $order->voucher->closed_at === null,
                'can_expire' => $order->voucher->state?->value === 'active'
                    && $order->voucher->redeemed_at === null
                    && $order->voucher->closed_at === null,
                'can_request_refund' => in_array($order->voucher->state?->value, ['expired', 'cancelled'], true)
                    && $order->voucher->redeemed_at === null,
            ]] : []),
            'activity' => $order->events->map(static fn ($event): array => [
                'sequence' => $event->sequence,
                'status' => $event->to_status->value,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ])->values()->all(),
            'redactions' => [
                'payer_identity' => true,
                'provider_payload' => true,
                'provider_credentials' => true,
                'cockpit_links' => true,
            ],
        ];
    }
}
