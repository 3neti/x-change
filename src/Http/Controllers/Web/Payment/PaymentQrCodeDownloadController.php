<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Payment;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentQrDeliveryMode;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Leads\CampaignDisplaySessions;
use LBHurtado\XChange\Services\Payment\PaymentAttemptSessionGuard;
use LBHurtado\XChange\Services\Payment\PaymentQrDeliveryPolicy;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentQrCodeDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        PaymentAttempt $attempt,
        PaymentAttemptSessionGuard $sessions,
        PaymentQrDeliveryPolicy $delivery,
        CampaignDisplaySessions $displays,
    ): StreamedResponse {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        abort_unless((string) $attempt->voucher_id === (string) $voucher->getKey(), 404);

        $browserKey = (string) $request->session()->get('x-change.payment.browser-key', '');
        $sessions->assertOwner($attempt, $browserKey);
        $display = $displays->forPayer($voucher, $request);

        abort_unless(
            $delivery->allows(
                $attempt,
                $voucher,
                $display !== null,
                PaymentQrDeliveryMode::Downloadable,
            ),
            404,
        );
        abort_unless(
            $attempt->status === PaymentAttemptStatus::AwaitingPayment
            && $attempt->expires_at !== null
            && ! $attempt->expires_at->isPast(),
            410,
        );

        $qr = data_get($attempt->instructions_ciphertext, 'qr_code');
        $payload = is_array($qr) ? data_get($qr, 'base64_payload') : null;
        $mimeType = is_array($qr) ? data_get($qr, 'mime_type') : null;
        $binary = is_string($payload) ? base64_decode($payload, true) : false;

        abort_unless($mimeType === 'image/png' && is_string($binary) && $binary !== '', 404);

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            sprintf('%s-qrph.png', strtoupper((string) $voucher->code)),
            [
                'Cache-Control' => 'no-store, private, max-age=0',
                'Content-Type' => 'image/png',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
