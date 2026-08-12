<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

class ClaimSurfaceComponentData extends Data
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public readonly string $type,
        public readonly array $props = [],
    ) {}
}
