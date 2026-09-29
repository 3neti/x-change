<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareCardUrlResolverContract;
use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use Throwable;

final readonly class OnDemandIssuanceFundingOrderPresenter
{
    public function __construct(
        private FundingInstructionPresenter $instructions,
        private FundingMethodSelectorCockpitReadModel $selectors,
        private ClaimUrlQrRendererContract $claimQr,
        private ClaimShareCardUrlResolverContract $shareCardUrls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(PayCodeIssuanceFundingOrder $order): array
    {
        $order->loadMissing(['fundingIntent.settlement', 'fundingIntent.events', 'voucher', 'events']);
        $instructions = $order->fundingIntent === null
            ? []
            : $this->instructions->forIntent($order->fundingIntent);

        return [
            'schema' => 'x-change.cockpit.on-demand-issuance-funding.v1',
            'status' => $order->status->value,
            'funding_required' => $order->on_demand_amount_minor > 0,
            'lifecycle' => $this->lifecycle($order),
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
                'voucher' => ! $order->voucher instanceof Voucher
                    ? null
                    : $this->voucher($order->voucher),
                'receipt' => $this->receipt($order),
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

    /**
     * @return array<string, mixed>
     */
    private function voucher(Voucher $voucher): array
    {
        $claimUrl = route('x-change.claim.show', ['code' => $voucher->code]);

        return [
            'code' => $voucher->code,
            'claim_url' => $claimUrl,
            'claim_qr' => $this->claimQr->render($claimUrl),
            'share_card_url' => $this->shareCardUrl($voucher),
            'detail_url' => Route::has('x-change.cockpit.pay-codes.show')
                ? route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code], false)
                : null,
        ];
    }

    private function shareCardUrl(Voucher $voucher): string
    {
        try {
            return $this->shareCardUrls->resolve($voucher);
        } catch (Throwable) {
            return route('x-change.claim.share-card', ['code' => $voucher->code]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lifecycle(PayCodeIssuanceFundingOrder $order): array
    {
        $current = match ($order->status->value) {
            'payer_acknowledged', 'verifying', 'underfunded', 'payment_ambiguous' => 'checking_payment',
            'funded' => 'payment_verified',
            'issuing' => 'issuing_pay_code',
            'issued' => 'pay_code_ready',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            'issuance_attention' => 'attention',
            default => 'awaiting_payment',
        };
        $latestIntentEvent = $order->fundingIntent?->events->last()?->event_type;
        $verificationUnavailable = $latestIntentEvent === 'provider_verification_unavailable';

        return [
            'current' => $current,
            'verification_unavailable' => $verificationUnavailable,
            'message' => $verificationUnavailable
                ? 'Verification is temporarily unavailable. You do not need to pay again.'
                : match ($current) {
                    'checking_payment' => 'Payment is not visible yet. You do not need to pay again.',
                    'payment_verified' => 'Payment verified. Your frozen instruction is ready for issuance.',
                    'issuing_pay_code' => 'Payment verified. Your Pay Code is being issued.',
                    'pay_code_ready' => 'Payment verified and Pay Code issued.',
                    'cancelled' => 'Funding was cancelled safely. No Pay Code was issued.',
                    'expired' => 'This funding order expired. Any late payment is handled separately and will not revive it.',
                    'attention' => 'This funding order needs attention. Do not make another payment.',
                    default => 'Transfer the exact amount, then ask x-change to check the payment.',
                },
            'steps' => collect([
                ['key' => 'awaiting_payment', 'label' => 'Awaiting payment'],
                ['key' => 'checking_payment', 'label' => 'Checking payment'],
                ['key' => 'payment_verified', 'label' => 'Payment verified'],
                ['key' => 'issuing_pay_code', 'label' => 'Issuing Pay Code'],
                ['key' => 'pay_code_ready', 'label' => 'Pay Code ready'],
            ])->map(function (array $step) use ($current): array {
                $order = ['awaiting_payment', 'checking_payment', 'payment_verified', 'issuing_pay_code', 'pay_code_ready'];
                $currentIndex = array_search($current, $order, true);
                $stepIndex = array_search($step['key'], $order, true);

                return [
                    ...$step,
                    'state' => ! is_int($currentIndex) || ! is_int($stepIndex)
                        ? 'pending'
                        : ($stepIndex < $currentIndex ? 'complete' : ($stepIndex === $currentIndex ? 'current' : 'pending')),
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receipt(PayCodeIssuanceFundingOrder $order): array
    {
        return [
            'order_reference' => $order->reference,
            'expected_payment_minor' => $order->expected_payment_minor,
            'currency' => $order->currency,
            'provider_transaction_id' => $order->fundingIntent?->provider_transaction_id,
            'verified_at' => $order->fundingIntent?->verified_at?->toIso8601String(),
            'settled_at' => $order->fundingIntent?->settlement?->settled_at?->toIso8601String(),
            'issued_at' => $order->issued_at?->toIso8601String(),
        ];
    }
}
