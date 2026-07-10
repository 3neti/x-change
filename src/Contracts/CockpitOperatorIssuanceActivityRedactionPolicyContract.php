<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;

interface CockpitOperatorIssuanceActivityRedactionPolicyContract
{
    public function redact(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityRecordData;
}
