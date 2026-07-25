<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

final readonly class FundingLiquidityRefreshData
{
    /**
     * @param  list<array{provider: string, status: string}>  $connections
     */
    public function __construct(
        public int $refreshed,
        public int $failed,
        public int $busy,
        public int $unavailable,
        public array $connections,
    ) {}

    public function succeeded(): bool
    {
        return $this->refreshed > 0;
    }

    public function hasIncompleteConnections(): bool
    {
        return ($this->failed + $this->busy + $this->unavailable) > 0;
    }
}
