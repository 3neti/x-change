<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\XChange\Data\Configuration\CommissioningStateData;

final class CommissioningManifestReadinessInspector
{
    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    public function inspect(CommissioningStateData $commissioning): array
    {
        return [
            'name' => 'installation manifest',
            'passed' => $commissioning->isOperational(),
            'message' => match ($commissioning->reason) {
                null => 'recorded installation matches the active deployment configuration',
                'deployment_configuration_incomplete' => 'deployment configuration must be completed before installation can be recorded',
                'installation_manifest_table_missing' => 'installation manifest storage has not been installed',
                'system_principal_account_incomplete' => 'installation cannot be commissioned until the System Account is ready',
                'installation_manifest_missing' => 'no installation manifest has been recorded',
                'installation_manifest_stale' => 'recorded installation does not match the active deployment configuration',
                default => 'commissioning state could not be verified',
            },
            'meta' => [
                'state' => $commissioning->state->value,
                'reason' => $commissioning->reason,
            ],
        ];
    }
}
