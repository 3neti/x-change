<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Settlement\SettlementExecutionResultData;

interface SettlementExecutionContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Voucher $voucher, array $payload): SettlementExecutionResultData;
}
