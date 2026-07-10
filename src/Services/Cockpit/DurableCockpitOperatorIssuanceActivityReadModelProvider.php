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
                    new CockpitOperatorIssuanceActivityJournalHandoffResultData(
                        status: $record->journal_handoff_status,
                        activity_id: $record->activity_id,
                        correlation_id: $record->correlation_id,
                    ),
                    new CockpitOperatorIssuanceActivityActionHandoffResultData(
                        status: $record->action_handoff_status,
                        activity_id: $record->activity_id,
                        correlation_id: $record->correlation_id,
                    ),
                    new CockpitOperatorIssuanceActivityFeedbackHandoffResultData(
                        status: $record->feedback_handoff_status,
                        activity_id: $record->activity_id,
                        correlation_id: $record->correlation_id,
                    ),
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
            empty_state: [
                'title' => 'No durable operator issuance activity available',
                'description' => 'Durable activity storage is configured, but no matching activity has been recorded yet.',
            ],
        );
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
}
