<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface ClaimOtpVerificationContract
{
    /**
     * @param  array<string, mixed>  $workflow
     */
    public function verify(
        Voucher $voucher,
        string $code,
        array $workflow
    ): bool;
}
