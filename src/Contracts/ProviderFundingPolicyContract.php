<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\FundingDecisionData;

interface ProviderFundingPolicyContract
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function assertCanIssue(mixed $owner, mixed $localWallet, float|int|string $amount, array $context = []): FundingDecisionData;
}
