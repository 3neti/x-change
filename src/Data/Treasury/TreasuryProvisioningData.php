<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;

final readonly class TreasuryProvisioningData
{
    /**
     * @param  list<TreasuryPositionData>  $positions
     * @param  list<string>  $skippedConnections
     */
    public function __construct(
        public TreasuryPreflightData $preflight,
        public array $positions,
        public array $skippedConnections,
    ) {}
}
