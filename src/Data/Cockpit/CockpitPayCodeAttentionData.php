<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitPayCodeAttentionData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $message,
        public readonly string $tone = 'critical',
    ) {}
}
