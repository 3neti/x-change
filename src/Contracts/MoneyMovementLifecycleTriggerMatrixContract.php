<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Money\MoneyMovementLifecycleTriggerMatrixData;

interface MoneyMovementLifecycleTriggerMatrixContract
{
    public function current(): MoneyMovementLifecycleTriggerMatrixData;
}
