<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Payment;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Payment\PaymentAttemptSessionGuard;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use Symfony\Component\HttpFoundation\Response;

class PaymentPageController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        VoucherFlowCapabilityResolverContract $capabilities,
        VoucherCollectionProgressService $progress,
        PaymentAttemptSessionGuard $sessions,
    ): Response {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        abort_unless($capabilities->resolve($voucher)->can_collect, 404);

        $collection = $progress->compute($voucher);
        $attempt = $this->attempt($request, $voucher, $sessions);
        $provider = strtolower((string) config('x-change.payment.attempts.provider', 'netbank'));
        $providerEnabled = (bool) config("x-change.funding.providers.{$provider}.enabled", false);

        $response = Inertia::render('x-change/claim/Payment', [
            'payment' => [
                'pay_code' => (string) $voucher->code,
                'currency' => $collection->currency,
                'target_amount_minor' => $collection->target_amount_minor,
                'collected_amount_minor' => $collection->collected_total_minor,
                'amount_due_minor' => $collection->remaining_to_collect_minor,
                'is_fully_paid' => $collection->is_fully_collected,
                'provider' => $provider,
                'provider_available' => $providerEnabled,
                'can_create_attempt' => (bool) config('x-change.payment.attempts.enabled', true)
                    && $providerEnabled
                    && ! $collection->is_fully_collected,
                'attempt' => $attempt === null ? null : $this->presentAttempt($attempt),
            ],
            'notice' => $request->session()->get('payment_notice'),
        ])->toResponse($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function attempt(
        Request $request,
        Voucher $voucher,
        PaymentAttemptSessionGuard $sessions,
    ): ?PaymentAttempt {
        $reference = trim((string) $request->query('attempt', ''));

        if ($reference === '') {
            return null;
        }

        $attempt = PaymentAttempt::query()
            ->where('reference', $reference)
            ->where('voucher_id', $voucher->getKey())
            ->firstOrFail();

        $browserKey = (string) $request->session()->get('x-change.payment.browser-key', '');
        $sessions->assertOwner($attempt, $browserKey);

        return $attempt;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAttempt(PaymentAttempt $attempt): array
    {
        $instructions = $attempt->instructions_ciphertext;
        $qr = is_array($instructions) ? data_get($instructions, 'qr_code') : null;

        return [
            'reference' => $attempt->reference,
            'status' => $attempt->status->value,
            'provider' => $attempt->provider_code,
            'amount_minor' => $attempt->expected_amount_minor,
            'currency' => $attempt->currency,
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'last_checked_at' => $attempt->last_checked_at?->toIso8601String(),
            'can_check' => $attempt->status === PaymentAttemptStatus::AwaitingPayment,
            'qr_code' => is_array($qr) ? [
                'mime_type' => data_get($qr, 'mime_type'),
                'base64_payload' => data_get($qr, 'base64_payload'),
                'qr_mode' => data_get($qr, 'qr_mode'),
                'transaction_type' => data_get($qr, 'transaction_type'),
                'embedded_amount' => (bool) data_get($qr, 'embedded_amount', false),
            ] : null,
        ];
    }
}
