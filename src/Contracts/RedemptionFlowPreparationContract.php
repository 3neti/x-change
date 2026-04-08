<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\PrepareRedemptionResultData;

interface RedemptionFlowPreparationContract
{
    public function prepare(Voucher $voucher): PrepareRedemptionResultData;
}
