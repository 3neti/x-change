<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

class ClaimSurfaceViewerData extends Data
{
    public function __construct(
        public readonly string $role,
        public readonly bool $authenticated,
    ) {}
}
