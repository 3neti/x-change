<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitActionReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitExecutionReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitFeedbackReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitJournalReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelBundleData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Data\Cockpit\CockpitVoucherReadModelData;

class NullCockpitReadModelProvider implements CockpitReadModelProviderContract
{
    public function forVoucher(CockpitReadModelQueryData $query): CockpitReadModelBundleData
    {
        return new CockpitReadModelBundleData(
            code: $query->code,
            voucher: new CockpitVoucherReadModelData(
                code: $query->code,
                status: 'not_wired',
            ),
            execution: new CockpitExecutionReadModelData(
                executionId: null,
                status: 'not_wired',
            ),
            journal: new CockpitJournalReadModelData(
                status: 'not_wired',
            ),
            actions: new CockpitActionReadModelData(
                status: 'not_wired',
            ),
            feedback: new CockpitFeedbackReadModelData(
                status: 'not_wired',
            ),
        );
    }
}
