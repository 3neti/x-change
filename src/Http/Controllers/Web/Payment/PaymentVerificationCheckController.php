<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Payment;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Jobs\Payment\VerifyPaymentAttemptJob;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Payment\PaymentAttemptSessionGuard;
use Throwable;

class PaymentVerificationCheckController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        PaymentAttempt $attempt,
        PaymentAttemptSessionGuard $sessions,
    ): RedirectResponse {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        abort_unless($attempt->voucher_id === $voucher->getKey(), 404);

        $browserKey = (string) $request->session()->get('x-change.payment.browser-key', '');
        $sessions->assertOwner($attempt, $browserKey);

        try {
            VerifyPaymentAttemptJob::dispatch(
                paymentAttemptId: (int) $attempt->getKey(),
                providerCode: $attempt->provider_code,
                trigger: PaymentVerificationTrigger::Payer,
            )->afterCommit();
            $checked = $attempt->fresh()->load('events');
        } catch (Throwable $exception) {
            $checked = $attempt->fresh();
            if ($checked?->status !== PaymentAttemptStatus::Verified) {
                throw $exception;
            }
            report($exception);
        }

        return redirect()
            ->route('x-change.pay.show', [
                'code' => $voucher->code,
                'attempt' => $checked->reference,
            ])
            ->with('payment_notice', $checked->version === $attempt->version
                && in_array($checked->status, [PaymentAttemptStatus::AwaitingPayment, PaymentAttemptStatus::Verifying], true)
                ? 'We are checking your payment. Please do not pay again.'
                : $this->notice($checked));
    }

    private function notice(PaymentAttempt $attempt): string
    {
        if ($attempt->status === PaymentAttemptStatus::AwaitingPayment
            && $attempt->events->sortByDesc('sequence')->first()?->event_type === 'provider_verification_unavailable') {
            return 'Payment verification is temporarily unavailable. Please do not pay again.';
        }

        return match ($attempt->status) {
            PaymentAttemptStatus::Settled => 'Payment confirmed from NetBank history.',
            PaymentAttemptStatus::Verified => 'Your bank payment is verified. Collection completion is pending. Do not pay again.',
            PaymentAttemptStatus::AwaitingPayment => 'No settled matching payment is visible yet.',
            PaymentAttemptStatus::Suspense => 'NetBank returned payment evidence that needs review.',
            PaymentAttemptStatus::Expired => 'This payment QR has expired.',
            default => 'NetBank verification is in progress.',
        };
    }
}
