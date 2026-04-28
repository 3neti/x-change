<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface ClaimOtpChallengeContract
{
    public function request(Voucher $voucher, array $workflow): array;
}
