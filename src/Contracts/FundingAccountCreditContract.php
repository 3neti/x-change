<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface FundingAccountCreditContract
{
    public function resolve(string $accountReference): object;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function credit(object $account, int $amountMinor, array $metadata): object;
}
