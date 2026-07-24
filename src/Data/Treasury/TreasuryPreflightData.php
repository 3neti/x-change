<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class TreasuryPreflightData
{
    /**
     * @param  list<TreasuryConnectionPreflightData>  $connections
     */
    public function __construct(
        public array $connections,
    ) {}

    public function passes(): bool
    {
        foreach ($this->connections as $connection) {
            if ($connection->blocksProvisioning()) {
                return false;
            }
        }

        return true;
    }
}
