<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateActionData extends Data
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $reason = 'not-loaded',
    ) {}
}
