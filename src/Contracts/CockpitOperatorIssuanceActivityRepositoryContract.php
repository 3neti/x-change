<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

interface CockpitOperatorIssuanceActivityRepositoryContract
{
    public function record(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityRecordData;

    public function findByActivityId(string $activityId): ?CockpitOperatorIssuanceActivityRecordData;

    /**
     * @return array<int, CockpitOperatorIssuanceActivityRecordData>
     */
    public function recent(CockpitReadModelQueryData $query, int $limit = 25): array;
}
