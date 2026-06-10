<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Claim;

use LBHurtado\Voucher\Models\Voucher;

interface ClaimApprovalOtpAuthorizer
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
     *     provider: string|null,
     *     messages: array<int, string>
     * }
     */
    public function authorize(Voucher $voucher, array $payload): array;
}
