<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;

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

        return back()->with([
            'approval_otp_received' => true,
            'approval_otp' => [
                'voucher_code' => (string) $voucher->code,
                'reference_id' => $validated['reference_id'] ?? null,
                'provider' => $validated['provider'] ?? null,
            ],
        ]);
    }
}
