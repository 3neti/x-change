<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Models\VoucherClaim;

final readonly class CampaignPayoutRecoveryNotificationEligibility
{
    public function isSuperseded(CampaignDeliveryAttempt $attempt): bool
    {
        if (data_get($attempt->metadata, 'purpose') !== 'beneficiary_payout_recovery') {
            return false;
        }

        $fulfillment = $attempt->fulfillment;
        if (! $fulfillment instanceof CampaignWorksheetFulfillment
            || $fulfillment->pay_code === null
            || data_get($fulfillment->metadata, 'fallback.mode') !== 'canonical_claim') {
            return false;
        }

        $voucher = Voucher::query()
            ->where('code', $fulfillment->pay_code)
            ->first();
        if (! $voucher instanceof Voucher) {
            return false;
        }

        $latestClaimStatus = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->value('status');

        return $latestClaimStatus !== 'payout_rejected';
    }
}
