<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Money\MoneyMovementTargetModelData;

interface MoneyMovementTargetModelContract
{
    public function current(): MoneyMovementTargetModelData;
}
