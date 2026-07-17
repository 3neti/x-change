<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitHeaderReadModelData;

interface CockpitHeaderReadModelProviderContract
{
    public function forOperator(mixed $operator = null): CockpitHeaderReadModelData;
}
