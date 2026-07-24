<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

use LBHurtado\XChange\Enums\TreasuryOpeningBalanceStatus;

final readonly class TreasuryOpeningBalanceReconciliationData
{
    /**
     * @param  list<TreasuryOpeningBalanceConnectionData>  $connections
     */
    public function __construct(
        public array $connections,
    ) {}

    public function passes(): bool
    {
        return collect($this->connections)->every(
            static fn (TreasuryOpeningBalanceConnectionData $connection): bool => $connection->status
                !== TreasuryOpeningBalanceStatus::ReviewRequired,
        );
    }
}
