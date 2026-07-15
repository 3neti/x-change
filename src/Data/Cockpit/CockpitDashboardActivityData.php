<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class CockpitDashboardActivityData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly string $timestamp,
        public readonly string $source,
        public readonly array|Optional $metadata = new Optional,
    ) {}
}
