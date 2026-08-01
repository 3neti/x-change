<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

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
}
