<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateTemplateData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly string $profile,
        public readonly string $estimated_time,
        public readonly bool $disabled = false,
    ) {}
}
