<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;

interface CampaignBankTransferStatusCheckerContract
{
    public function check(CampaignWorksheetFulfillment $fulfillment): CampaignBankTransferDispatchResult;
}
