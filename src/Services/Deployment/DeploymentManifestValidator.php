<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use RuntimeException;

final readonly class DeploymentManifestValidator
{
    private const Targets = ['local', 'laravel-cloud', 'forge', 'custom'];

    private const Operations = [
        'configure',
        'preflight',
        'install',
        'verify',
        'validate',
        'platform-deploy',
        'commission',
    ];

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function validate(array $manifest): void
    {
        if (($manifest['schema'] ?? null) !== DeploymentManifestGenerator::Schema) {
            throw new RuntimeException('Unsupported x-change deployment manifest schema.');
        }

        $target = $manifest['deployment']['target'] ?? null;

        if (! is_string($target) || ! in_array($target, self::Targets, true)) {
            throw new RuntimeException('Unsupported x-change deployment target.');
        }

        $secretKeys = $manifest['environment']['secrets']['keys'] ?? null;

        if (! is_array($secretKeys) || array_filter($secretKeys, fn ($key): bool => ! is_string($key)) !== []) {
            throw new RuntimeException('Deployment secret declarations must contain key names only.');
        }

        foreach ((array) ($manifest['operations'] ?? []) as $operations) {
            foreach ((array) $operations as $operation) {
                if (! is_string($operation) || ! in_array($operation, self::Operations, true)) {
                    throw new RuntimeException('Deployment manifest contains an undeclared operation.');
                }
            }
        }

        if (($manifest['safety']['write_production_env'] ?? null) !== false) {
            throw new RuntimeException('Deployment manifests must prohibit production .env writes.');
        }

        if (($manifest['safety']['automatic_database_reset'] ?? null) !== false) {
            throw new RuntimeException('Deployment manifests must prohibit automatic database resets.');
        }
    }
}
