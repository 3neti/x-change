<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class TreasuryConnectionPreflightData
{
    /**
     * @param  list<string>  $issues
     */
    public function __construct(
        public TreasuryProviderConnectionData $connection,
        public bool $ready,
        public array $issues = [],
    ) {}

    public function blocksProvisioning(): bool
    {
        return $this->connection->isRequired() && ! $this->ready;
    }
}
