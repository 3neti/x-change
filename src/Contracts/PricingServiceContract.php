<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Data\VoucherInstructionsData;

interface PricingServiceContract
{
    /**
     * @return array{
     *     currency:string,
     *     base_fee:float,
     *     components:array<string,float>,
     *     total:float
     * }
     */
    public function estimate(VoucherInstructionsData $instructions): array;
}
