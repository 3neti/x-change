<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;

final class SubmitClaimApprovalOtp
{
    /**
     * @param  array{
     *     otp: string,
     *     reference_id?: string|null,
     *     provider?: string|null
     * }  $payload
     * @return array{
     *     status: string,
     *     voucher_code: string,
     *     reference_id: string|null,
     *     provider: string|null
     * }
     */
    public function handle(Voucher $voucher, array $payload): array
    {
        return [
            'status' => 'received',
            'voucher_code' => (string) $voucher->code,
            'reference_id' => $payload['reference_id'] ?? null,
            'provider' => $payload['provider'] ?? null,
        ];
    }
}
