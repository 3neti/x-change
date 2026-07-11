<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitDistributionWorkspaceItemData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $status,
        public readonly string $description,
        public readonly bool $read_only = true,
        public readonly bool $available = false,
        public readonly ?string $source = null,
        public readonly ?string $href = null,
        public readonly array $metadata = [],
    ) {}
}
