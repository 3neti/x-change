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

        $filters = $query->operatorActivityFilters;

        if ($filters === null || $filters->isEmpty()) {
            return true;
        }

        if ($filters->statuses !== [] && ! in_array($record->status, $filters->statuses, true)) {
            return false;
        }

        if ($filters->handoffStatuses !== [] && ! $this->matchesHandoffStatus($record, $filters->handoffStatuses)) {
            return false;
        }

        if ($filters->search !== null && ! $this->matchesSearch($record, $filters->search)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, string>  $handoffStatuses
     */
    private function matchesHandoffStatus(
        CockpitOperatorIssuanceActivityRecordData $record,
        array $handoffStatuses,
    ): bool {
        return in_array($record->journal_handoff_status, $handoffStatuses, true)
            || in_array($record->action_handoff_status, $handoffStatuses, true)
            || in_array($record->feedback_handoff_status, $handoffStatuses, true);
    }

    private function matchesSearch(CockpitOperatorIssuanceActivityRecordData $record, string $search): bool
    {
        $haystack = implode(' ', array_filter([
            $record->activity_id,
            $record->actor_id,
            $record->actor_label,
            $record->subject_reference,
            $record->status,
            $record->severity,
            $record->correlation_id,
            $record->summary,
            is_scalar(data_get($record->safe_context, 'amount')) ? (string) data_get($record->safe_context, 'amount') : null,
            is_scalar(data_get($record->safe_context, 'currency')) ? (string) data_get($record->safe_context, 'currency') : null,
        ], fn (mixed $value): bool => is_scalar($value) && (string) $value !== ''));

        return str_contains(mb_strtolower($haystack), mb_strtolower($search));
    }
}
