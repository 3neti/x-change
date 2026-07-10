<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

class InMemoryCockpitOperatorIssuanceActivityRepository implements CockpitOperatorIssuanceActivityRepositoryContract
{
    /**
     * @var array<string, CockpitOperatorIssuanceActivityRecordData>
     */
    protected array $records = [];

    public function record(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityRecordData
    {
        $this->records[$record->activity_id] = $record;

        return $record;
    }

    public function findByActivityId(string $activityId): ?CockpitOperatorIssuanceActivityRecordData
    {
        return $this->records[$activityId] ?? null;
    }

    /**
     * @return array<int, CockpitOperatorIssuanceActivityRecordData>
     */
    public function recent(CockpitReadModelQueryData $query, int $limit = 25): array
    {
        $records = array_filter(
            $this->records,
            fn (CockpitOperatorIssuanceActivityRecordData $record): bool => $this->matchesQuery($record, $query),
        );

        usort(
            $records,
            fn (CockpitOperatorIssuanceActivityRecordData $left, CockpitOperatorIssuanceActivityRecordData $right): int => strcmp(
                $right->occurred_at ?? '',
                $left->occurred_at ?? '',
            ),
        );

        return array_slice(array_values($records), 0, max(0, $limit));
    }

    protected function matchesQuery(
        CockpitOperatorIssuanceActivityRecordData $record,
        CockpitReadModelQueryData $query,
    ): bool {
        if ($query->operatorId !== null && $record->actor_id !== $query->operatorId) {
            return false;
        }

        if ($query->correlationId !== null && $record->correlation_id !== $query->correlationId) {
            return false;
        }

        if ($query->code !== null && $record->subject_reference !== $query->code) {
            return false;
        }

        return true;
    }
}
