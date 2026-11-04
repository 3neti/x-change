<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\EmiCore\Contracts\PendingPayoutApproval;
use LBHurtado\Voucher\Models\Voucher;

final class PendingPaynamicsOtpClaimResult
{
    /**
     * @return array<string, mixed>
     */
    public function fromException(Voucher $voucher, PendingPayoutApproval $exception): array
    {
        $metadata = $exception->toApprovalMetadata();

        return [
            'status' => 'approval_required',
            'voucher_code' => (string) $voucher->code,
            'requirements' => ['otp'],
            'reference_id' => $exception->requestId(),
            'provider' => $exception->provider(),
            'messages' => [
                'Payout OTP approval required.',
            ],
            'meta' => $metadata,
            'approval_metadata' => $metadata,
        ];
    }
}
