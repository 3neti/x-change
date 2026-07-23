<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;

interface FundingDestinationResolverContract
{
    public function resolve(mixed $owner, string $provider, string $accountReference): FundingDestinationData;

    public function shared(string $provider, string $accountReference): FundingDestinationData;
}
