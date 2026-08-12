<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

class ClaimSurfaceActionData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $href = null,
        public readonly string $method = 'get',
        public readonly string $variant = 'secondary',
    ) {}
}
