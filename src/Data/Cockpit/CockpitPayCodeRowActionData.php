<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitPayCodeRowActionData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $enabled,
        public readonly bool $read_only = true,
        public readonly ?string $href = null,
        public readonly ?string $reason = null,
    ) {}
}
