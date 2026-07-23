<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Funding\FundingAccountRecoveryData;

interface FundingAccountRecoveryContract
{
    public function resolve(string $accountReference): object;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recover(object $account, int $amountMinor, array $metadata): FundingAccountRecoveryData;
}
