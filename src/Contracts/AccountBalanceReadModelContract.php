<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface AccountBalanceReadModelContract
{
    public function balanceMinor(mixed $owner, string $currency): ?int;

    public function providerBalanceMinor(
        mixed $owner,
        string $provider,
        string $currency,
    ): ?int;
}
