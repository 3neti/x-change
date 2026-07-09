<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;

interface CockpitOperatorIssuanceActivityRecorderContract
{
    public function record(CockpitOperatorIssuanceActivityItemData $activity): void;
}
