<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use LBHurtado\EmiCore\Contracts\ProviderProvisioningProfile;
use LogicException;

final class ProviderProvisioningProfileResolver
{
    public function for(string $provider): ?ProviderProvisioningProfile
    {
        $matches = [];
        foreach (app()->tagged('emi.provider-provisioning-profiles') as $candidate) {
            if ($candidate instanceof ProviderProvisioningProfile && strtolower(trim($candidate->providerCode())) === strtolower(trim($provider))) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) > 1) {
            throw new LogicException('Multiple provider provisioning profiles are registered.');
        }

        return $matches[0] ?? null;
    }
}
