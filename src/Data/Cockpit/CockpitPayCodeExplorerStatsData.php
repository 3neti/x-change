<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitPayCodeExplorerStatsData extends Data
{
    public function __construct(
        public readonly int $total = 0,
        public readonly int $active = 0,
        public readonly int $awaiting_approval = 0,
        public readonly int $redeemed = 0,
        public readonly int $expired = 0,
        public readonly int $pending = 0,
        public readonly int $failed = 0,
        public readonly int $filtered = 0,
    ) {}
}
