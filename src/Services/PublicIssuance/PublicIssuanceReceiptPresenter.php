<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final readonly class PublicIssuanceReceiptPresenter
{
    public function __construct(private PublicIssuanceVoucherActionPolicy $actionPolicy) {}

    /**
     * @return array<string, mixed>
     */
    public function present(
        PayCodeIssuanceFundingOrder $order,
        bool $canManage = false,
        ?IssuanceBatch $batch = null,
    ): array
    {
        $order->loadMissing(['voucher', 'events']);

        $voucher = $order->voucher;
        $batchItem = null;

        if (! $voucher instanceof Voucher && $batch instanceof IssuanceBatch) {
            $batchItem = $batch->items()
                ->whereNotNull('voucher_id')
                ->first();
            $voucher = $batchItem?->voucher_id === null
                ? null
                : Voucher::query()->find($batchItem->voucher_id);
        }

        $payCode = $voucher instanceof Voucher ? [
            'code' => (string) $voucher->code,
            'claim_url' => route('x-change.claim.show', [
                'code' => $voucher->code,
            ]),
            'detail_url' => $canManage && $batch instanceof IssuanceBatch
                ? route('x-change.public-auto-generate.batches.pay-codes.detail', [
                    'batch' => $batch->reference,
                    'code' => $voucher->code,
                ], false)
                : null,
            'state' => $voucher->state?->value,
            'expires_at' => $voucher->expires_at?->toIso8601String(),
        ] : null;

        return [
            'schema' => 'x-change.public-issuance-receipt.v1',
            'order_reference' => $order->reference,
            'status' => $order->status->value,
            'amount_minor' => $order->expected_payment_minor,
            'currency' => $order->currency,
            'created_at' => $order->created_at?->toIso8601String(),
            'verified_at' => $order->funded_at?->toIso8601String(),
            'issued_at' => $order->issued_at?->toIso8601String(),
            'pay_code' => $payCode,
            ...($canManage && $voucher instanceof Voucher ? ['actions' => [
                'url' => $batch instanceof IssuanceBatch
                    ? route('x-change.public-auto-generate.batches.pay-codes.action', [
                        'batch' => $batch->reference,
                        'code' => $voucher->code,
                    ], false)
                    : route('x-change.public-auto-generate.issuances.action', ['order' => $order->reference], false),
                ...$this->actionPolicy->permissions($voucher),
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
