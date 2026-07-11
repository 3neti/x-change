<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use Throwable;

class CockpitOperatorIssuanceActivityHandoffPipeline
{
    public function __construct(
        private readonly CockpitOperatorIssuanceActivityRecorderContract $recorder,
        private readonly CockpitOperatorIssuanceActivityJournalHandoffContract $journalHandoff,
        private readonly CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract $journalHandoffStatusProjector,
        private readonly CockpitOperatorIssuanceActivityActionHandoffContract $actionHandoff,
        private readonly CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract $actionHandoffStatusProjector,
    ) {}

    public function process(CockpitOperatorIssuanceActivityItemData $activity): void
    {
        try {
            $this->recorder->record($activity);
        } catch (Throwable) {
            return;
        }

        $result = $this->journalHandoffResult($activity);

        try {
            $this->journalHandoffStatusProjector->project($result);
        } catch (Throwable) {
            //
        }

        $actionResult = $this->actionHandoffResult($activity);

        try {
            $this->actionHandoffStatusProjector->project($actionResult);
        } catch (Throwable) {
            //
        }
    }

    private function journalHandoffResult(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityJournalHandoffResultData
    {
        try {
            return $this->journalHandoff->handoff($activity);
        } catch (Throwable $exception) {
            return new CockpitOperatorIssuanceActivityJournalHandoffResultData(
                status: 'failed_non_blocking',
                activity_id: $activity->id,
                correlation_id: $activity->correlation_id,
                writes_journal: false,
                source: 'cockpit-operator-issuance-activity-handoff-pipeline',
                reason: 'Journal handoff invocation failed without blocking the Cockpit activity flow.',
                metadata: [
                    'exception' => $exception::class,
                ],
            );
        }
    }

    private function actionHandoffResult(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityActionHandoffResultData
    {
        try {
            return $this->actionHandoff->handoff($activity);
        } catch (Throwable $exception) {
            return new CockpitOperatorIssuanceActivityActionHandoffResultData(
                status: 'failed_non_blocking',
                activity_id: $activity->id,
                correlation_id: $activity->correlation_id,
                action_required: false,
                executes_action: false,
                source: 'cockpit-operator-issuance-activity-handoff-pipeline',
                reason: 'Action handoff invocation failed without blocking the Cockpit activity flow.',
                metadata: [
                    'exception' => $exception::class,
                ],
            );
        }
    }
}
