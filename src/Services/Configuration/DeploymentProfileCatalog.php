<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\XChange\Data\Configuration\DeploymentProfileData;
use RuntimeException;

final readonly class DeploymentProfileCatalog
{
    public function __construct(private DeploymentConnectionCatalog $connections) {}

    public function resolve(?string $name = null): DeploymentProfileData
    {
        $name = mb_strtolower(trim($name ?? (string) config(
            'x-change.deployment.profile',
            'development',
        )));

        $references = match ($name) {
            'development' => [],
            'netbank' => ['netbank-primary'],
            'paynamics' => ['paynamics-primary'],
            'hybrid' => ['netbank-primary', 'paynamics-primary'],
            'custom' => $this->customConnectionReferences(),
            default => throw new RuntimeException("Unknown x-change deployment profile [{$name}]."),
        };

        if (
            $name === 'development'
            && mb_strtolower((string) config('app.env')) === 'production'
        ) {
            throw new RuntimeException('The development deployment profile is forbidden in production.');
        }

        $templates = $this->connections->templates();
        $missing = array_values(array_diff($references, array_keys($templates)));

        if ($missing !== []) {
            throw new RuntimeException(
                'Deployment profile requires unavailable connections: '.implode(', ', $missing).'.',
            );
        }

        $providers = array_values(array_unique(array_map(
            static fn (string $reference): string => $templates[$reference]->provider,
            $references,
        )));
        sort($providers);

        return new DeploymentProfileData(
            name: $name,
            connectionReferences: $references,
            providerCodes: $providers,
            productionAllowed: $name !== 'development',
        );
    }

    /**
     * @return list<string>
     */
    private function customConnectionReferences(): array
    {
        $references = array_values(array_unique(array_filter(array_map(
            static fn (mixed $reference): string => trim((string) $reference),
            (array) config('x-change.deployment.custom_connections', []),
        ))));

        if ($references === []) {
            throw new RuntimeException(
                'The custom deployment profile requires XCHANGE_ACTIVE_CONNECTIONS.',
            );
        }

        return $references;
    }
}
