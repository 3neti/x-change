<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityPresenterContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityPresentationData;

class DefaultCockpitOperatorIssuanceActivityPresenter implements CockpitOperatorIssuanceActivityPresenterContract
{
    public function __construct(
        private readonly CockpitOperatorIssuanceActivityJournalHandoffDiagnostics $journalHandoffDiagnostics,
    ) {}

    public function present(
        CockpitOperatorIssuanceActivityItemData $activity,
        CockpitOperatorIssuanceActivityJournalHandoffResultData $journal,
        CockpitOperatorIssuanceActivityActionHandoffResultData $action,
        CockpitOperatorIssuanceActivityFeedbackHandoffResultData $feedback,
    ): CockpitOperatorIssuanceActivityPresentationData {
        return new CockpitOperatorIssuanceActivityPresentationData(
            id: $activity->id,
            code: $activity->code,
            title: sprintf('Pay Code %s %s', $activity->code, $activity->status),
            subtitle: sprintf('%s %s issued through Quick Generate', $activity->currency, $activity->amount),
            status: $activity->status,
            detail_href: $activity->detail_href,
            correlation_id: $activity->correlation_id,
            handoffs: [
                'journal' => $journal->status,
                'action' => $action->status,
                'feedback' => $feedback->status,
            ],
            metadata: [
                'journal_handoff' => [
                    'status' => $journal->status,
                    'journal_entry_id' => $journal->journal_entry_id,
                    'writes_journal' => $journal->writes_journal,
                    'source' => $journal->source,
                    'reason' => $journal->reason,
                    'metadata' => $journal->metadata,
                    'diagnostic' => $this->journalHandoffDiagnostics->classify($journal),
                ],
                'action_handoff' => [
                    'status' => $action->status,
                    'action_hint_id' => $action->action_hint_id,
                    'action_run_id' => $action->action_run_id,
                    'action_required' => $action->action_required,
                    'executes_action' => $action->executes_action,
                    'source' => $action->source,
                    'reason' => $action->reason,
                    'metadata' => $action->metadata,
                ],
            ],
        );
    }
}
