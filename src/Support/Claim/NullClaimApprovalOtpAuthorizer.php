<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer;

final class NullClaimApprovalOtpAuthorizer implements ClaimApprovalOtpAuthorizer
{
    public function authorize(Voucher $voucher, array $payload): array
    {
        return [
            'status' => 'received',
            'voucher_code' => (string) $voucher->code,
            'reference_id' => $payload['reference_id'] ?? null,
            'provider' => $payload['provider'] ?? null,
            'messages' => [
                'Approval OTP received.',
            ],
        ];
    }
}
