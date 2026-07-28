<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\RiderStampCopyData;

interface RiderStampCopyResolverContract
{
    public function resolve(Voucher $voucher): RiderStampCopyData;
}
