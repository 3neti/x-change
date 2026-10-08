<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\StandingFundingAddressSyncStatus;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Models\StandingFundingAddressState;

final class CampaignPaymentMonitoringEligibility
{
    public function reason(CampaignPaymentQrBinding $binding): ?string
    {
        $binding->loadMissing(['campaign', 'standingFundingAddress']);

        if ($binding->entry_mode !== CampaignEntryMode::ReusablePaymentQr) {
            return 'unsupported_entry_mode';
        }

        if ($binding->campaign?->status !== 'active') {
            return 'campaign_not_active';
        }

        if ($binding->available_from?->isFuture() || $binding->available_until?->isPast()) {
            return 'binding_not_available';
        }

        $address = $binding->standingFundingAddress;

        if ($address === null || $address->purpose !== FundingAddressPurpose::Payment) {
            return 'not_payment_address';
        }

        if ($address->status !== FundingAddressStatus::Active) {
            return 'address_not_active';
        }

        $state = StandingFundingAddressState::query()
            ->where('standing_funding_address_id', $address->getKey())
            ->first();

        if ($state !== null && in_array(
            $state->status,
            [StandingFundingAddressSyncStatus::Quarantined, StandingFundingAddressSyncStatus::Ambiguous],
            true,
        )) {
            return $state->status->value;
        }

        return null;
    }

    public function isEligible(CampaignPaymentQrBinding $binding): bool
    {
        return $this->reason($binding) === null;
    }
}
