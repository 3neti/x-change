<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;

final readonly class TreasurySystemPositionCatalog
{
    /**
     * @return array<string, TreasuryPositionPurpose>
     */
    public function all(): array
    {
        return [
            'clearing' => TreasuryPositionPurpose::TreasuryClearing,
            'institution-owned-funds' => TreasuryPositionPurpose::InstitutionOwnedFunds,
            'unattributed' => TreasuryPositionPurpose::LegacyUnattributed,
            'account-funding-reserve' => TreasuryPositionPurpose::AccountFundingReserve,
            'commercial-clearing' => TreasuryPositionPurpose::CommercialClearing,
            'provider-cost-payable' => TreasuryPositionPurpose::ProviderCostPayable,
            'product-revenue' => TreasuryPositionPurpose::ProductRevenue,
            'partner-commission-payable' => TreasuryPositionPurpose::PartnerCommissionPayable,
            'royalty-payable' => TreasuryPositionPurpose::RoyaltyPayable,
            'tax-payable' => TreasuryPositionPurpose::TaxPayable,
            'commercial-revenue' => TreasuryPositionPurpose::CommercialRevenue,
        ];
    }
}
