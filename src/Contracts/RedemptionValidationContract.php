<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Models\Voucher;

interface RedemptionValidationContract
{
    public function validate(Voucher $voucher, RedemptionContext $context): void;
}
