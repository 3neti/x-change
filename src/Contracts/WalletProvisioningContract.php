<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface WalletProvisioningContract
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function open(mixed $issuer, array $input): mixed;
}
