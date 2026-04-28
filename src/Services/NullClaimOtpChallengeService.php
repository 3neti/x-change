<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimOtpChallengeContract;

class NullClaimOtpChallengeService implements ClaimOtpChallengeContract
{
    public function request(Voucher $voucher, array $workflow): array
    {
        return [
            'driver' => 'null',
            'requested' => false,
        ];
    }
}
