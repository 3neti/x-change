<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityPresentationData;

interface CockpitOperatorIssuanceActivityPresenterContract
{
    public function present(
        CockpitOperatorIssuanceActivityItemData $activity,
        CockpitOperatorIssuanceActivityJournalHandoffResultData $journal,
        CockpitOperatorIssuanceActivityActionHandoffResultData $action,
        CockpitOperatorIssuanceActivityFeedbackHandoffResultData $feedback,
    ): CockpitOperatorIssuanceActivityPresentationData;
}
