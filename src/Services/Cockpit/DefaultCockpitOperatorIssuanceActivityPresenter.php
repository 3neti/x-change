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
        $metadata = [
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
            'feedback_handoff' => [
                'status' => $feedback->status,
                'feedback_intent_id' => $feedback->feedback_intent_id,
                'delivery_plan_id' => $feedback->delivery_plan_id,
                'delivery_receipt_id' => $feedback->delivery_receipt_id,
                'feedback_required' => $feedback->feedback_required,
                'sends_feedback' => $feedback->sends_feedback,
                'source' => $feedback->source,
                'reason' => $feedback->reason,
                'metadata' => $feedback->metadata,
            ],
        ];

        $campaignAttribution = $this->safeCampaignAttribution(data_get($activity->metadata, 'campaign_attribution', []));

        if ($campaignAttribution !== []) {
            $metadata['campaign_attribution'] = $campaignAttribution;
        }

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
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function safeCampaignAttribution(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        return array_filter(array_intersect_key($metadata, array_flip([
            'schema',
            'status',
            'read_only',
            'mutates_campaign',
            'planning_key',
            'execution_id',
            'campaign_id',
            'audience_id',
            'recipient_id',
            'source',
            'generated_code',
            'template_key',
            'amount',
            'currency',
            'recipient_reference',
            'purpose',
            'redactions',
        ])), fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
