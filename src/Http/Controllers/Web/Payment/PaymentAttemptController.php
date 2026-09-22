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
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Leads\CampaignDisplaySessions;
use LBHurtado\XChange\Services\Payment\PaymentQrDeliveryPolicy;
use Throwable;

class PaymentAttemptController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        CreatePaymentAttempt $create,
        IssuePaymentInstructions $issue,
        CampaignDisplaySessions $displays,
        PaymentQrDeliveryPolicy $qrDelivery,
    ): RedirectResponse {
        abort_unless((bool) config('x-change.payment.attempts.enabled', true), 404);

        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        $display = $displays->forPayer($voucher, $request);
        $qrDeliveryModes = $qrDelivery->forVoucher($voucher, $display !== null);
        if ($display !== null) {
            $displays->assertOpen($display);
            abort_unless($displays->intakeReady($display, $voucher), 409, 'Complete the application before choosing payment.');
        }

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
            $issueAttempt = function () use ($create, $issue, $voucher, $browserKey, $idempotencyKey, $qrDeliveryModes, $display, $displays): PaymentAttempt {
                if ($display !== null) {
                    $displays->assertOpen($display->fresh());
                }
                $attempt = $create->handle(
                    voucher: $voucher,
                    provider: (string) config('x-change.payment.attempts.provider', 'netbank'),
                    browserKey: $browserKey,
                    idempotencyKey: $idempotencyKey,
                    qrDeliveryModes: $qrDeliveryModes,
                );

                if ($display !== null) {
                    $displays->attachAttempt($display, $attempt);
                }

                return $issue->handle($attempt);
            };
            $attempt = $display === null ? $issueAttempt() : $displays->synchronized($display, $issueAttempt);
        } catch (VoucherRequiresSettlementEnvelope) {
            return redirect()
                ->route('x-change.pay.show', ['code' => $voucher->code])
                ->with('payment_notice', 'Complete the required application evidence before paying.');
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
