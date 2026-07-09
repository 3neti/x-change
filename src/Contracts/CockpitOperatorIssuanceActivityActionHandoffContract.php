<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;

interface CockpitOperatorIssuanceActivityActionHandoffContract
{
    public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityActionHandoffResultData;
}
