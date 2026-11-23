<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Data\Funding\OnDemandIssuanceFundingRequirementData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Services\Banking\SelectedAccountClientFunds;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use RuntimeException;

final readonly class OnDemandIssuanceFundingRequirement
{
    public function __construct(
        private OnDemandIssuanceFundingPolicy $policy,
        private BuildBalanceOverview $balances,
    ) {}

    public function for(Model $issuer, PricingEstimateData $pricing, ?int $selectedBalanceMinor = null): OnDemandIssuanceFundingRequirementData
    {
        return $this->forBasis($issuer, $pricing, $this->policy->basis(), $selectedBalanceMinor);
    }

    public function forBasis(
        Model $issuer,
        PricingEstimateData $pricing,
        OnDemandIssuanceFundingBasis $basis,
        ?int $selectedBalanceMinor = null,
    ): OnDemandIssuanceFundingRequirementData {
        $requiredAmountMinor = (int) round(
            ($pricing->account_debit ?? ($pricing->pay_code_value ?? 0) + $pricing->total) * 100,
        );

        if ($requiredAmountMinor < 0) {
            throw new RuntimeException('On-demand issuance requires a non-negative authoritative amount.');
        }

        $availableClientFundsMinor = $basis === OnDemandIssuanceFundingBasis::Shortfall
            ? ($selectedBalanceMinor ?? $this->availableClientFundsMinor($issuer))
            : 0;
        $reservedClientFundsMinor = min($requiredAmountMinor, max(0, $availableClientFundsMinor));

        return new OnDemandIssuanceFundingRequirementData(
            basis: $basis,
            requiredAmountMinor: $requiredAmountMinor,
            reservedClientFundsMinor: $reservedClientFundsMinor,
            externalAmountMinor: $requiredAmountMinor - $reservedClientFundsMinor,
        );
    }

    private function availableClientFundsMinor(Model $issuer): int
    {
        $selected = app(SelectedAccountClientFunds::class)->forOwner($issuer);
        if ($selected['amount_minor'] === null) {
            throw new RuntimeException('The selected account Client Funds balance is unavailable.');
        }

        return $selected['amount_minor'];
    }
}
