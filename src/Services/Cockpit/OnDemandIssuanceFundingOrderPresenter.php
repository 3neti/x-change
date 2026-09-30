<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareCardUrlResolverContract;
use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
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
                'verify' => route(
                    'x-change.cockpit.quick-generate.funding-orders.verification',
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
                'late_payment_disposition' => $order->late_payment_disposition,
                'late_payment_detected_at' => $order->late_payment_detected_at?->toIso8601String(),
                'voucher' => ! $order->voucher instanceof Voucher
                    ? null
                    : $this->voucher($order->voucher, $order),
                'receipt' => $this->receipt($order),
            ],
            'monitor' => $this->monitor($order),
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
    private function voucher(Voucher $voucher, PayCodeIssuanceFundingOrder $order): array
    {
        $claimUrl = route('x-change.claim.show', ['code' => $voucher->code]);
        $amount = data_get($order->instructions_ciphertext, 'cash.amount', 0);
        $currency = data_get($order->instructions_ciphertext, 'cash.currency', $order->currency);

        return [
            'code' => $voucher->code,
            'amount' => is_numeric($amount) ? (float) $amount : 0.0,
            'currency' => is_string($currency) && $currency !== '' ? $currency : $order->currency,
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

        $latePaymentCredited = $order->status === PayCodeIssuanceFundingOrderStatus::Expired
            && $order->late_payment_disposition === 'client_funds';

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
                    'expired' => $latePaymentCredited
                        ? 'Your payment arrived after this order expired and was added to Client Funds. No Pay Code was issued.'
                        : 'This order expired. Do not pay these instructions. No Pay Code was issued.',
                    'attention' => 'This funding order needs attention. Do not make another payment.',
                    default => 'Pay the exact amount once. We will check for it automatically.',
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
     * @return array<string, bool|int|string|null>
     */
    private function monitor(PayCodeIssuanceFundingOrder $order): array
    {
        $enabled = (bool) config(
            'x-change.issuance_funding.on_demand.automatic_verification.enabled',
            true,
        );
        $intervalSeconds = max(
            5,
            (int) config(
                'x-change.issuance_funding.on_demand.automatic_verification.interval_seconds',
                10,
            ),
        );
        $intent = $order->fundingIntent;
        $eligible = $intent !== null
            && in_array($order->status, [
                PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
                PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
                PayCodeIssuanceFundingOrderStatus::Verifying,
                PayCodeIssuanceFundingOrderStatus::Underfunded,
                PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
            ], true)
            && in_array($intent->status, [
                FundingIntentStatus::AwaitingFunds,
                FundingIntentStatus::EvidenceReceived,
                FundingIntentStatus::Verifying,
                FundingIntentStatus::Verified,
            ], true);
        $latestVerification = $intent?->events
            ->filter(static fn ($event): bool => in_array($event->event_type, [
                'provider_verification_started',
                'provider_funds_not_observed',
                'provider_verification_unavailable',
                'provider_settlement_pending',
                'provider_settlement_verified',
            ], true))
            ->last();

        return [
            'enabled' => $enabled,
            'eligible' => $eligible,
            'interval_milliseconds' => $intervalSeconds * 1000,
            'last_checked_at' => $latestVerification?->occurred_at?->toIso8601String(),
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
