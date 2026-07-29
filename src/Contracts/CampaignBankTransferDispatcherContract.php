<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;

interface CampaignBankTransferDispatcherContract
{
    /**
     * Dispatches one already-authorized, provider-ready fulfillment.
     * Implementations must be idempotent on the fulfillment reference.
     */
    public function dispatch(CampaignWorksheetFulfillment $fulfillment): CampaignBankTransferDispatchResult;
}
