<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Data\Funding\OnDemandIssuanceFundingRequirementData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use RuntimeException;

final readonly class OnDemandIssuanceFundingRequirement
{
    public function __construct(
        private OnDemandIssuanceFundingPolicy $policy,
        private BuildBalanceOverview $balances,
    ) {}

    public function for(Model $issuer, PricingEstimateData $pricing): OnDemandIssuanceFundingRequirementData
    {
        return $this->forBasis($issuer, $pricing, $this->policy->basis());
    }

    public function forBasis(
        Model $issuer,
        PricingEstimateData $pricing,
        OnDemandIssuanceFundingBasis $basis,
    ): OnDemandIssuanceFundingRequirementData {
        $requiredAmountMinor = (int) round(
            ($pricing->account_debit ?? ($pricing->pay_code_value ?? 0) + $pricing->total) * 100,
        );

        if ($requiredAmountMinor <= 0) {
            throw new RuntimeException('On-demand issuance requires a positive authoritative amount.');
        }

        $availableClientFundsMinor = $basis === OnDemandIssuanceFundingBasis::Shortfall
            ? $this->availableClientFundsMinor($issuer)
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
        $overview = $this->balances->handle($issuer, syncIfStale: false);

        return (int) data_get(
            collect((array) data_get($overview, 'balances', []))->firstWhere('key', 'local_ledger'),
            'balance_minor',
            0,
        );
    }
}
