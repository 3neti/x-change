<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use Spatie\LaravelData\Data;

final class TreasuryAccountPortfolioData extends Data
{
    /**
     * @param  list<TreasuryPositionData>  $positions
     * @param  list<string>  $skippedConnections
     */
    public function __construct(
        public readonly string $principalReference,
        public readonly array $positions,
        public readonly array $skippedConnections,
    ) {}
}
