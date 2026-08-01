<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

final readonly class CommissioningRecoveryGuide
{
    /**
     * @param  array{name: string, passed: bool, message: string, meta: array<string, mixed>}  $check
     * @return array{title: string, description: string, command: string, reference_guidance: string, verification_command: string}|null
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
                '--system-principal-authorization-reference="deployment:<stable-control-reference>"',
                '--confirm-system-principal',
                '--force',
                '--no-interaction',
            ]),
            'reference_guidance' => 'Replace <stable-control-reference> with the deployment or approved change-ticket reference. Reuse that same reference if the command is retried.',
            'verification_command' => 'php artisan x-change:doctor --strict --no-interaction',
        ];
    }
}
