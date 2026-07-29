<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Contracts\CampaignBankTransferDispatchResult;
use LBHurtado\XChange\Contracts\CampaignBankTransferStatusCheckerContract;

final class NullCampaignBankTransferStatusChecker implements CampaignBankTransferStatusCheckerContract
{
    public function check(CampaignWorksheetFulfillment $fulfillment): CampaignBankTransferDispatchResult
    {
        return new CampaignBankTransferDispatchResult('blocked', reason: 'No campaign bank-transfer status checker is enabled.');
    }
}
