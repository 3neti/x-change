<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitPayCodeExplorerFilterData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $value,
        public readonly bool $active = false,
        public readonly bool $read_only = true,
    ) {}
}
