<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Services\Cockpit\FundingInstructionPresenter;
use LBHurtado\XChange\Services\Payment\PayCodePaymentLinkResolver;

final readonly class PartnerFundedOrderPresenter
{
    public function __construct(private FundingInstructionPresenter $fundingInstructions, private PayCodePaymentLinkResolver $paymentLinks) {}

    /** @return array<string, mixed> */
    public function present(Checkout $checkout): array
    {
        $checkout->loadMissing('fundingOrder.fundingIntent', 'fundingOrder.voucher', 'refundCase');
        $order = $checkout->fundingOrder;
        $intent = $order?->fundingIntent;
        $instructions = $intent === null ? [] : $this->fundingInstructions->forIntent($intent);
        $voucher = $order?->voucher;
        $method = $checkout->selected_method;
        $acceptsPayment = in_array($order?->status->value, [
            'awaiting_payment', 'payer_acknowledged', 'verifying',
        ], true) && $order?->expires_at?->isFuture() === true;
        $needsReview = in_array($order?->status->value, [
            'underfunded', 'payment_ambiguous', 'issuance_attention',
        ], true) || $checkout->refundCase !== null
            || ($order?->status === PayCodeIssuanceFundingOrderStatus::Issued && ! $voucher instanceof Voucher);

        return [
            'schema' => 'x-change.partner-funded-order.v1',
            'reference' => $order?->reference,
            'checkout_reference' => $checkout->reference,
            'external_reference' => data_get($order?->instructions_ciphertext, 'metadata.custom.external_reference'),
            'status' => $order?->status->value,
            'phase' => $needsReview ? 'review_required' : match ($order?->status) {
                PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
                PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
                PayCodeIssuanceFundingOrderStatus::Verifying => $order?->expires_at?->isPast() ? 'expired' : 'payment_pending',
                PayCodeIssuanceFundingOrderStatus::Funded => $order?->on_demand_amount_minor === 0 ? 'funded_from_client_funds' : 'payment_verified',
                PayCodeIssuanceFundingOrderStatus::Issuing => 'issuance_pending',
                PayCodeIssuanceFundingOrderStatus::Issued => $voucher instanceof Voucher ? 'issued' : 'review_required',
                PayCodeIssuanceFundingOrderStatus::Cancelled => 'cancelled',
                PayCodeIssuanceFundingOrderStatus::Expired => 'expired',
                default => 'review_required',
            },
            'funding_method' => $method,
            'amount_minor' => $order?->expected_payment_minor,
            'currency' => $order?->currency,
            'expires_at' => $order?->expires_at?->toIso8601String(),
            'payment_required' => $acceptsPayment,
            'funding' => ! $acceptsPayment ? null : ($method === 'qr_ph' ? [
                'qr_code' => $instructions['qr_code'] ?? null,
                'embedded_amount' => $instructions['embedded_amount'] ?? false,
                'reference' => $instructions['reference'] ?? null,
            ] : [
                'bank_account' => $instructions['funding_address'] ?? null,
                'institution' => $instructions['institution'] ?? null,
                'account_name' => $instructions['account_name'] ?? null,
                'reference' => $instructions['reference'] ?? null,
            ]),
            'pay_code' => $voucher instanceof Voucher ? [
                'code' => (string) $voucher->code,
                'claim_url' => route('x-change.claim.show', ['code' => $voucher->code]),
                'pay_url' => $this->paymentLinks->forVoucher($voucher)['pay'],
            ] : null,
            'needs_review' => $needsReview,
            'refund_status' => $checkout->refundCase?->status,
        ];
    }
}
