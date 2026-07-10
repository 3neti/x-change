<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;

interface CockpitOperatorIssuanceActivityRetentionPolicyContract
{
    public function retentionUntil(CockpitOperatorIssuanceActivityRecordData $record): ?string;

    public function isRetainable(CockpitOperatorIssuanceActivityRecordData $record): bool;
}
