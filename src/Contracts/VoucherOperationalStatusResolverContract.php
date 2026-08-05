<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\PayCode\PayCodeOperationalStatusData;

interface VoucherOperationalStatusResolverContract
{
    public function resolve(
        Voucher $voucher,
        bool $claimed,
        bool $fullyClaimed,
        bool $approvalRequired = false,
    ): PayCodeOperationalStatusData;
}
