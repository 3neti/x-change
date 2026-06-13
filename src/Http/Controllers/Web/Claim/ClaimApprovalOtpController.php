<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Support\Claim\ClaimApprovalOtpResultRedirector;
use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayload;
use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayloadSession;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;

final class ClaimApprovalOtpController
{
    public function __construct(
        private readonly ClaimApprovalResumePayloadSession $resumePayloadSession,
        private readonly ClaimApprovalResumePayload $resumePayload,
        private readonly SubmitWebPayCodeClaim $submitWebPayCodeClaim,
    ) {}

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        $validated = $request->validate([
            'otp' => ['required', 'string'],
            'reference_id' => ['nullable', 'string'],
            'provider' => ['nullable', 'string'],
        ]);

        $result = app(SubmitClaimApprovalOtp::class)->handle($voucher, $validated);

        if (($result['status'] ?? null) === 'completed') {
            $basePayload = $this->resumePayloadSession->get($voucher);

            if ($basePayload) {
                $resumePayload = $this->resumePayload->build($voucher, array_replace_recursive(
                    $basePayload,
                    $validated,
                ));

                $result = $this->submitWebPayCodeClaim->handle($voucher, $resumePayload);

                $this->resumePayloadSession->forget($voucher);
            }
        }

        $redirectResult = is_array($result)
            ? $result
            : $result->toArray();

        app(CompiledClaimResultSession::class)->put((object) $redirectResult);

        return app(ClaimApprovalOtpResultRedirector::class)->redirect($voucher, $redirectResult);
    }
}
