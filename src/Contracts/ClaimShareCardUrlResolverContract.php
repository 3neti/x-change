<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface ClaimShareCardUrlResolverContract
{
    public function resolve(Voucher $voucher): string;
}
