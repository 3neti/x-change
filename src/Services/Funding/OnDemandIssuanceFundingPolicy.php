<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use InvalidArgumentException;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;

final readonly class OnDemandIssuanceFundingPolicy
{
    public function enabled(): bool
    {
        return (bool) config('x-change.issuance_funding.on_demand.enabled', false);
    }

    public function basis(): OnDemandIssuanceFundingBasis
    {
        $basis = OnDemandIssuanceFundingBasis::tryFrom((string) config(
            'x-change.issuance_funding.on_demand.basis',
            OnDemandIssuanceFundingBasis::FullAmount->value,
        ));

        if ($basis === null) {
            throw new InvalidArgumentException('The on-demand issuance funding basis is invalid.');
        }

        return $basis;
    }
}
