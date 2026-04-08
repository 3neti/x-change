<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;

interface RedemptionExecutionContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function redeem(Voucher $voucher, array $payload): RedeemPayCodeResultData;
}
