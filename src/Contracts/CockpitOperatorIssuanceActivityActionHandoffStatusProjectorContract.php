<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData;

interface CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract
{
    public function project(CockpitOperatorIssuanceActivityActionHandoffResultData $result): CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData;
}
