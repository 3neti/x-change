<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface TreasuryPositionLedgerResolverContract
{
    public function resolve(string $positionReference): object;
}
