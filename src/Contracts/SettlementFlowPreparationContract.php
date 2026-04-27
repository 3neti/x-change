<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Settlement\PrepareSettlementResultData;

interface SettlementFlowPreparationContract
{
    public function prepare(Voucher $voucher): PrepareSettlementResultData;
}
