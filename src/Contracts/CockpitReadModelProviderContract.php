<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitReadModelBundleData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

interface CockpitReadModelProviderContract
{
    public function forVoucher(CockpitReadModelQueryData $query): CockpitReadModelBundleData;
}
