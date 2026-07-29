<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Contracts\CampaignBankTransferDispatchResult;
use LBHurtado\XChange\Contracts\CampaignBankTransferStatusCheckerContract;

final readonly class NetbankCampaignBankTransferStatusChecker implements CampaignBankTransferStatusCheckerContract
{
    public function __construct(private PayoutProvider $payouts) {}

    public function check(CampaignWorksheetFulfillment $fulfillment): CampaignBankTransferDispatchResult
    {
        if (! config('x-change.campaigns.netbank_dispatch.enabled', false) || blank($fulfillment->provider_transfer_reference)) {
            return new CampaignBankTransferDispatchResult('blocked', reason: 'NetBank campaign status checking is unavailable.');
        }

        $result = $this->payouts->checkStatus((string) $fulfillment->provider_transfer_reference);

        return new CampaignBankTransferDispatchResult(
            match ($result->status) {
                PayoutStatus::COMPLETED => 'completed',
                PayoutStatus::FAILED, PayoutStatus::CANCELLED, PayoutStatus::REFUNDED => 'failed',
                default => 'pending',
            },
            $result->transaction_id,
            $result->status->getLabel(),
        );
    }
}
