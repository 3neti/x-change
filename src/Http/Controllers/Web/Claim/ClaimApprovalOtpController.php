<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp;

final class ClaimApprovalOtpController
{
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

        return back()->with([
            'approval_otp_received' => true,
            'approval_otp' => $result,
        ]);
    }
}
