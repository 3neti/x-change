<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Money\MoneyMovementAccountingDecisionData;

interface MoneyMovementAccountingDecisionContract
{
    public function current(): MoneyMovementAccountingDecisionData;
}
