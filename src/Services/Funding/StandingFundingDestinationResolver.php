<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;

final class StandingFundingDestinationResolver
{
    public function __construct(
        private readonly FundingDestinationResolverContract $destinations,
    ) {}

    public function resolve(Model $owner, string $accountReference): FundingDestinationData
    {
        return $this->destinations->resolve($owner, 'netbank', $accountReference);
    }
}
