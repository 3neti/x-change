<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

final readonly class DeploymentManifestUpgrader
{
    public function __construct(
        private CloudRecipeRepository $recipes,
        private DeploymentManifestHasher $hasher,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function upgrade(array $manifest): array
    {
        if (($manifest['schema'] ?? null) === DeploymentManifestGenerator::LegacySchema) {
            $recipe = $this->recipes->read();
            $manifest['schema'] = DeploymentManifestGenerator::Schema;
            $manifest['recipe'] = [
                'schema' => $recipe['schema'],
                'version' => $recipe['version'],
                'target' => $recipe['target'],
                'hash' => $this->recipes->hash(),
            ];
        }

        $manifest['manifest_hash'] = $this->hasher->hash($manifest);

        return $manifest;
    }
}
