<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceTemplateProfileData;

interface CockpitIssuanceTemplateRegistryContract
{
    public function resolve(string $key): ?CockpitIssuanceTemplateProfileData;

    /**
     * @return array<int, CockpitIssuanceTemplateProfileData>
     */
    public function all(): array;
}
