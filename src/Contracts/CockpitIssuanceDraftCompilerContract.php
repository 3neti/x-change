<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;

interface CockpitIssuanceDraftCompilerContract
{
    /**
     * @return array<string, mixed>
     */
    public function compile(CockpitIssuanceDraftData $draft): array;
}
