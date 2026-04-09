<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;

interface WithdrawalExecutionContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function withdraw(Voucher $voucher, array $payload): WithdrawPayCodeResultData;
}
