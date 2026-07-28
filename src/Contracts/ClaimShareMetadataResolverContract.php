<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\ClaimShareMetadataData;

interface ClaimShareMetadataResolverContract
{
    public function resolve(
        Voucher $voucher,
        string $claimUrl,
        string $shareCardUrl,
    ): ClaimShareMetadataData;
}
