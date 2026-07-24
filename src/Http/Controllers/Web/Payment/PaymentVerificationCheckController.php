<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Payment;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Payment\PaymentAttemptSessionGuard;

class PaymentVerificationCheckController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        PaymentAttempt $attempt,
        PaymentAttemptSessionGuard $sessions,
        VerifyPaymentAttempt $verify,
    ): RedirectResponse {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        abort_unless($attempt->voucher_id === $voucher->getKey(), 404);

        $browserKey = (string) $request->session()->get('x-change.payment.browser-key', '');
        $sessions->assertOwner($attempt, $browserKey);

        $checked = $verify->handle($attempt, PaymentVerificationTrigger::Payer);

        return redirect()
            ->route('x-change.pay.show', [
                'code' => $voucher->code,
                'attempt' => $checked->reference,
            ])
            ->with('payment_notice', $this->notice($checked->status));
    }

    private function notice(PaymentAttemptStatus $status): string
    {
        return match ($status) {
            PaymentAttemptStatus::Settled => 'Payment confirmed from NetBank history.',
            PaymentAttemptStatus::AwaitingPayment => 'No settled matching payment is visible yet.',
            PaymentAttemptStatus::Suspense => 'NetBank returned payment evidence that needs review.',
            PaymentAttemptStatus::Expired => 'This payment QR has expired.',
            default => 'NetBank verification is in progress.',
        };
    }
}
