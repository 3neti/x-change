<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;

class NullClaimOtpVerificationService implements ClaimOtpVerificationContract
{
    public function verify(Voucher $voucher, string $code, array $workflow): bool
    {
        return false;
    }
}
