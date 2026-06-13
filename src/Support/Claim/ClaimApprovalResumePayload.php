<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\Voucher\Models\Voucher;

final class ClaimApprovalResumePayload
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function build(Voucher $voucher, array $payload): array
    {
        return array_replace_recursive($payload, [
            'approval' => [
                'resume' => true,
                'provider' => $payload['provider'] ?? 'paynamics',
                'reference_id' => $payload['reference_id'] ?? null,
                'authorization_type' => 'otp',
            ],
            'otp' => [
                'verified' => true,
                'code' => $payload['otp'] ?? $payload['otp_code'] ?? null,
            ],
            'voucher_code' => (string) $voucher->code,
        ]);
    }
}
