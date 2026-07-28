<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\ClaimShareCardData;

interface RiderStampClaimCardComposerContract
{
    public function compose(
        Voucher $voucher,
        string $claimUrl,
    ): ClaimShareCardData;
}
