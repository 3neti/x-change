<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\XChange\Data\Configuration\CommissioningStateData;
use LBHurtado\XChange\Enums\CommissioningState;

final readonly class CommissioningRecoveryGuide
{
    /**
     * @param  array{name: string, passed: bool, message: string, meta: array<string, mixed>}  $check
     * @return array{title: string, description: string, command: string, verification_command: string}|null
     */
    public function forSystemPrincipalAccount(array $check): ?array
    {
        if ($check['passed']) {
            return null;
        }

        return [
            'title' => 'Complete the System Account',
            'description' => 'Run the guarded installer to provision the configured system principal, initialize Treasury, and record the commissioning manifest.',
            'command' => implode(" \\\n  ", [
                'php artisan x-change:install',
                '--provision-system-principal',
                '--confirm-system-principal',
                '--force',
                '--no-interaction',
            ]),
            'verification_command' => 'php artisan x-change:doctor --strict --no-interaction',
        ];
    }

    /**
     * @return array{title: string, description: string, command: string, verification_command: string}|null
     */
    public function forCommissioningState(
        CommissioningStateData $commissioning,
        bool $systemPrincipalAccountReady,
    ): ?array {
        if ($commissioning->isOperational() || ! $systemPrincipalAccountReady) {
            return null;
        }

        if (
            $commissioning->state === CommissioningState::InstallationIncomplete
            && in_array($commissioning->reason, [
                'installation_manifest_missing',
                'installation_manifest_stale',
            ], true)
        ) {
            return [
                'title' => $commissioning->reason === 'installation_manifest_stale'
                    ? 'Confirm the Updated Configuration'
                    : 'Record the Existing Installation',
                'description' => $commissioning->reason === 'installation_manifest_stale'
                    ? 'The recorded installation no longer matches the active sanitized deployment configuration. If this configuration change is intentional, run the guarded adoption command. It revalidates the System Account and Treasury before recording a new fingerprint.'
                    : 'The application is configured and its System Account exists, but no installation manifest is recorded. Adopt the verified existing installation after confirming its Treasury state.',
                'command' => 'php artisan x-change:commissioning:adopt --confirm-existing-installation --no-interaction',
                'verification_command' => 'php artisan x-change:doctor --strict --no-interaction',
            ];
        }

        if ($commissioning->state === CommissioningState::ReadyToInstall) {
            return [
                'title' => 'Install X-Change',
                'description' => 'Configuration is ready, but the installation manifest table is not available yet. Run the guarded installer.',
                'command' => 'php artisan x-change:install --force --no-interaction',
                'verification_command' => 'php artisan x-change:doctor --strict --no-interaction',
            ];
        }

        return null;
    }
}
