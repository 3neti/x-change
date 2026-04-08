<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Models\Voucher;

interface RedemptionProcessorContract
{
    public function process(Voucher $voucher, RedemptionContext $context): bool;
}
