<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Contracts\CampaignBankTransferDispatcherContract;
use LBHurtado\XChange\Contracts\CampaignBankTransferDispatchResult;

final class NullCampaignBankTransferDispatcher implements CampaignBankTransferDispatcherContract
{
    public function dispatch(CampaignWorksheetFulfillment $fulfillment): CampaignBankTransferDispatchResult
    {
        return new CampaignBankTransferDispatchResult('blocked', reason: 'No campaign bank-transfer dispatcher is enabled.');
    }
}
