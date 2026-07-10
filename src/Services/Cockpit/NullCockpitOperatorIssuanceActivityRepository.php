<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

class NullCockpitOperatorIssuanceActivityRepository implements CockpitOperatorIssuanceActivityRepositoryContract
{
    public function record(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityRecordData
    {
        return $record;
    }

    public function findByActivityId(string $activityId): ?CockpitOperatorIssuanceActivityRecordData
    {
        return null;
    }

    /**
     * @return array<int, CockpitOperatorIssuanceActivityRecordData>
     */
    public function recent(CockpitReadModelQueryData $query, int $limit = 25): array
    {
        return [];
    }
}
