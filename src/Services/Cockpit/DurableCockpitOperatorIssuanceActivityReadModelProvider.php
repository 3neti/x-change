<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityPresenterContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivitySearchFilterData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

class DurableCockpitOperatorIssuanceActivityReadModelProvider
{
    public function __construct(
        private readonly CockpitOperatorIssuanceActivityRepositoryContract $activities,
        private readonly CockpitOperatorIssuanceActivityPresenterContract $presenter,
    ) {}

    public function forOperator(CockpitReadModelQueryData $query): CockpitOperatorIssuanceActivityReadModelData
    {
        if ($this->activities instanceof NullCockpitOperatorIssuanceActivityRepository) {
            return new CockpitOperatorIssuanceActivityReadModelData;
        }

        $records = $this->activities->recent($query);

        $items = collect($records)
            ->map(fn (CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityItemData => $this->toItem($record))
            ->values()
            ->all();

        $presentations = collect($records)
            ->map(function (CockpitOperatorIssuanceActivityRecordData $record, int $index) use ($items): mixed {
                $item = $items[$index] ?? null;

                if (! $item instanceof CockpitOperatorIssuanceActivityItemData) {
                    return null;
                }

                return $this->presenter->present(
                    $item,
                    $this->journalHandoffResult($record),
                    $this->actionHandoffResult($record),
                    $this->feedbackHandoffResult($record),
                );
            })
            ->filter()
            ->values()
            ->all();

        return new CockpitOperatorIssuanceActivityReadModelData(
            status: 'available',
            authorized: true,
            source: 'durable-operator-issuance-activity-read-model',
            items: $items,
            presentations: $presentations,
            search_filters: $this->searchFilters($query, $records),
            empty_state: [
                'title' => 'No durable operator issuance activity available',
                'description' => 'Durable activity storage is configured, but no matching activity has been recorded yet.',
            ],
        );
    }

    /**
     * @param  array<int, CockpitOperatorIssuanceActivityRecordData>  $records
     * @return array<string, mixed>
     */
    private function searchFilters(CockpitReadModelQueryData $query, array $records): array
    {
        $filters = $query->operatorActivityFilters ?? new CockpitOperatorIssuanceActivitySearchFilterData;

        return [
            'schema' => 'x-change.cockpit.operator-issuance-activity-search-filter.v1',
            'status' => 'available',
            'read_only' => true,
            'search' => $filters->search,
            'statuses' => $filters->statuses,
            'handoff_statuses' => $filters->handoffStatuses,
            'available_statuses' => collect($records)
                ->pluck('status')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'available_handoff_statuses' => collect($records)
                ->flatMap(fn (CockpitOperatorIssuanceActivityRecordData $record): array => [
                    $record->journal_handoff_status,
                    $record->action_handoff_status,
                    $record->feedback_handoff_status,
                ])
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'safety' => [
                'read_only' => true,
                'writes_journal' => false,
                'executes_actions' => false,
                'sends_feedback' => false,
                'moves_money' => false,
                'owns_lifecycle_truth' => false,
            ],
        ];
    }

    private function toItem(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityItemData
    {
        $detailHref = data_get($record->safe_context, 'detail_href');

        return new CockpitOperatorIssuanceActivityItemData(
            id: $record->activity_id,
            code: (string) $record->subject_reference,
            amount: (string) data_get($record->safe_context, 'amount', ''),
            currency: (string) data_get($record->safe_context, 'currency', ''),
            status: $record->status,
            issued_at: (string) $record->occurred_at,
            route: (string) data_get($record->safe_context, 'route', $record->source),
            correlation_id: $record->correlation_id,
            idempotency_key: null,
            operator_id: $record->actor_id,
            detail_href: is_string($detailHref) ? $detailHref : null,
            metadata: [
                'source' => $record->source,
                'durable_record' => true,
            ],
        );
    }

    private function journalHandoffResult(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityJournalHandoffResultData
    {
        $summary = $this->journalHandoffSummary($record);

        return new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: (string) data_get($summary, 'status', $record->journal_handoff_status),
            activity_id: $record->activity_id,
            correlation_id: $record->correlation_id,
            journal_entry_id: $this->nullableString(data_get($summary, 'journal_entry_id')),
            writes_journal: (bool) data_get($summary, 'writes_journal', false),
            source: (string) data_get($summary, 'source', 'durable-operator-issuance-activity-read-model'),
            reason: (string) data_get($summary, 'reason', 'Journal handoff status is projected from durable Cockpit activity storage.'),
            metadata: $this->safeJournalHandoffMetadata(data_get($summary, 'metadata', [])),
        );
    }

    private function actionHandoffResult(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityActionHandoffResultData
    {
        $summary = $this->actionHandoffSummary($record);

        return new CockpitOperatorIssuanceActivityActionHandoffResultData(
            status: (string) data_get($summary, 'status', $record->action_handoff_status),
            activity_id: $record->activity_id,
            correlation_id: $record->correlation_id,
            action_hint_id: $this->nullableString(data_get($summary, 'action_hint_id')),
            action_run_id: $this->nullableString(data_get($summary, 'action_run_id')),
            action_required: (bool) data_get($summary, 'action_required', false),
            executes_action: (bool) data_get($summary, 'executes_action', false),
            source: (string) data_get($summary, 'source', 'durable-operator-issuance-activity-read-model'),
            reason: (string) data_get($summary, 'reason', 'Action handoff status is projected from durable Cockpit activity storage.'),
            metadata: $this->safeActionHandoffMetadata(data_get($summary, 'metadata', [])),
        );
    }

    private function feedbackHandoffResult(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityFeedbackHandoffResultData
    {
        $summary = $this->feedbackHandoffSummary($record);

        return new CockpitOperatorIssuanceActivityFeedbackHandoffResultData(
            status: (string) data_get($summary, 'status', $record->feedback_handoff_status),
            activity_id: $record->activity_id,
            correlation_id: $record->correlation_id,
            feedback_intent_id: $this->nullableString(data_get($summary, 'feedback_intent_id')),
            delivery_plan_id: $this->nullableString(data_get($summary, 'delivery_plan_id')),
            delivery_receipt_id: $this->nullableString(data_get($summary, 'delivery_receipt_id')),
            feedback_required: (bool) data_get($summary, 'feedback_required', false),
            sends_feedback: (bool) data_get($summary, 'sends_feedback', false),
            source: (string) data_get($summary, 'source', 'durable-operator-issuance-activity-read-model'),
            reason: (string) data_get($summary, 'reason', 'Feedback handoff status is projected from durable Cockpit activity storage.'),
            metadata: $this->safeFeedbackHandoffMetadata(data_get($summary, 'metadata', [])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function journalHandoffSummary(CockpitOperatorIssuanceActivityRecordData $record): array
    {
        $summary = data_get($record->metadata, 'journal_handoff');

        return is_array($summary) ? $summary : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionHandoffSummary(CockpitOperatorIssuanceActivityRecordData $record): array
    {
        $summary = data_get($record->metadata, 'action_handoff');

        return is_array($summary) ? $summary : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackHandoffSummary(CockpitOperatorIssuanceActivityRecordData $record): array
    {
        $summary = data_get($record->metadata, 'feedback_handoff');

        return is_array($summary) ? $summary : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeJournalHandoffMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        return array_intersect_key($metadata, array_flip([
            'reference_number',
            'event_type',
            'idempotency_key',
            'exception',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function safeActionHandoffMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $safe = array_intersect_key($metadata, array_flip([
            'event_or_state',
            'actions',
            'composition',
            'safe_diagnostics',
            'exception',
        ]));

        if (isset($safe['actions']) && is_array($safe['actions'])) {
            $safe['actions'] = array_values(array_map(
                fn (mixed $action): array => $this->safeActionMetadata($action),
                $safe['actions'],
            ));
        }

        return $safe;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeActionMetadata(mixed $action): array
    {
        if (! is_array($action)) {
            return [];
        }

        return array_intersect_key($action, array_flip([
            'key',
            'label',
            'intent',
            'description',
            'run_id',
            'target',
            'run_semantics',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function safeFeedbackHandoffMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $safe = array_intersect_key($metadata, array_flip([
            'intent_key',
            'event_type',
            'delivery_boundary',
            'planned_deliveries',
            'channels',
            'plan_items',
            'composition',
            'exception',
        ]));

        if (isset($safe['plan_items']) && is_array($safe['plan_items'])) {
            $safe['plan_items'] = array_values(array_map(
                fn (mixed $item): array => $this->safeFeedbackPlanItemMetadata($item),
                $safe['plan_items'],
            ));
        }

        return $safe;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeFeedbackPlanItemMetadata(mixed $item): array
    {
        if (! is_array($item)) {
            return [];
        }

        return array_intersect_key($item, array_flip([
            'intent_key',
            'recipient_type',
            'recipient_id',
            'channel',
            'status',
            'priority',
            'correlation_id',
            'causation_id',
        ]));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
