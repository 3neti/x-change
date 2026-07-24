<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Payment;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CreatePaymentAttempt;
use LBHurtado\XChange\Actions\Payment\IssuePaymentInstructions;
use Throwable;

class PaymentAttemptController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        CreatePaymentAttempt $create,
        IssuePaymentInstructions $issue,
    ): RedirectResponse {
        abort_unless((bool) config('x-change.payment.attempts.enabled', true), 404);

        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        $browserKeySession = 'x-change.payment.browser-key';
        $browserKey = (string) $request->session()->get($browserKeySession, '');

        if ($browserKey === '') {
            $browserKey = Str::random(64);
            $request->session()->put($browserKeySession, $browserKey);
        }

        $sessionKey = 'x-change.payment.attempt-idempotency.'.$voucher->getKey();
        $idempotencyKey = (string) $request->session()->get($sessionKey, '');

        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
            $request->session()->put($sessionKey, $idempotencyKey);
        }

        try {
            $attempt = $create->handle(
                voucher: $voucher,
                provider: (string) config('x-change.payment.attempts.provider', 'netbank'),
                browserKey: $browserKey,
                idempotencyKey: $idempotencyKey,
            );

            $attempt = $issue->handle($attempt);
        } catch (Throwable) {
            return redirect()
                ->route('x-change.pay.show', ['code' => $voucher->code])
                ->with(
                    'payment_notice',
                    'NetBank could not create payment instructions. No payment was recorded. Please try again.',
                );
        }

        return redirect()->route('x-change.pay.show', [
            'code' => $voucher->code,
            'attempt' => $attempt->reference,
        ]);
    }
}
