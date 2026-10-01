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
    public function present(PayCodeIssuanceFundingOrder $order): array
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
            ] : null,
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
