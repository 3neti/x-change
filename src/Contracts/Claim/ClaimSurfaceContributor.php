<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Claim;

use LBHurtado\XChange\Data\Claim\ClaimSurfaceContextData;
use LBHurtado\XChange\Services\Claim\ClaimSurfaceBuilder;

interface ClaimSurfaceContributor
{
    public function contribute(
        ClaimSurfaceBuilder $surface,
        ClaimSurfaceContextData $context,
    ): void;
}
