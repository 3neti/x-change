<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftValidationResultData;

interface CockpitIssuanceDraftValidatorContract
{
    public function validate(CockpitIssuanceDraftData $draft): CockpitIssuanceDraftValidationResultData;
}
