<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Http\RedirectResponse;
use LBHurtado\Voucher\Models\Voucher;

final class ClaimApprovalOtpResultRedirector
{
    public function redirect(Voucher $voucher, array $result): RedirectResponse
    {
        $status = (string) ($result['status'] ?? '');

        if (in_array($status, ['success', 'completed'], true)) {
            return redirect()->route('x-change.claim.success', [
                'code' => $voucher->code,
            ]);
        }

        if (in_array($status, ['pending', 'received'], true)) {
            return redirect()->route('x-change.claim.approval', [
                'code' => $voucher->code,
            ]);
        }

        if ($status === 'failed') {
            return back()->withErrors([
                'otp' => $this->failureMessage($result),
            ]);
        }

        throw new \RuntimeException("Unsupported approval OTP result status [{$status}].");
    }

    private function failureMessage(array $result): string
    {
        $messages = $result['messages'] ?? [];

        if (is_array($messages) && isset($messages[0]) && is_string($messages[0]) && $messages[0] !== '') {
            return $messages[0];
        }

        return 'Unable to verify OTP.';
    }
}
