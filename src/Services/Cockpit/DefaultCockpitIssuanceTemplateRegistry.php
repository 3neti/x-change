<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitIssuanceTemplateRegistryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceTemplateProfileData;

class DefaultCockpitIssuanceTemplateRegistry implements CockpitIssuanceTemplateRegistryContract
{
    public function resolve(string $key): ?CockpitIssuanceTemplateProfileData
    {
        return collect($this->all())
            ->first(fn (CockpitIssuanceTemplateProfileData $profile): bool => $profile->key === $key);
    }

    public function all(): array
    {
        return [
            new CockpitIssuanceTemplateProfileData(
                key: 'money-changer',
                name: 'Money Changer',
                profile: 'branch',
                default_feedback: ['mobile' => null],
                default_rider: ['message' => 'Your Pay Code is ready.'],
                metadata: ['purpose' => 'branch-counter-cash-out'],
            ),
            new CockpitIssuanceTemplateProfileData(
                key: 'ofw-remittance',
                name: 'OFW Remittance',
                profile: 'operations',
                default_input_fields: ['mobile'],
                default_validation: ['mobile' => null],
                default_feedback: ['mobile' => null],
                default_rider: ['message' => 'Your remittance Pay Code is ready.'],
                metadata: ['purpose' => 'remittance'],
            ),
            new CockpitIssuanceTemplateProfileData(
                key: 'settlement-envelope',
                name: 'Settlement Envelope',
                profile: 'settlement',
                enabled: false,
                metadata: ['purpose' => 'settlement-envelope-deferred'],
            ),
        ];
    }
}
