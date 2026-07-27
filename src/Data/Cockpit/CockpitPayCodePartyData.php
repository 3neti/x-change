<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

final class CockpitPayCodePartyData extends Data
{
    public function __construct(
        public readonly string $state,
        public readonly string $label,
        public readonly string $primary,
        public readonly ?string $secondary = null,
        public readonly bool $masked = false,
    ) {}
}
