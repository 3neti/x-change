<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\RiderStampRecipientData;

interface RiderStampRecipientResolverContract
{
    public function resolve(Voucher $voucher): RiderStampRecipientData;
}
