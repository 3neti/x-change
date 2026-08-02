<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Composer\InstalledVersions;
use LBHurtado\XChange\Services\Configuration\DeploymentEnvironmentCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentProfileCatalog;
use LBHurtado\XChange\Services\Configuration\HostApplicationIdentity;
use LBHurtado\XChange\Services\Configuration\RuntimeOperationsChecklist;

final readonly class DeploymentManifestGenerator
{
    public const LegacySchema = '3neti.x-change.deployment.v1';

    public const Schema = '3neti.x-change.deployment.v2';

    public function __construct(
        private DeploymentProfileCatalog $profiles,
        private DeploymentEnvironmentCatalog $environment,
        private HostApplicationIdentity $identity,
        private RuntimeOperationsChecklist $runtime,
        private CloudRecipeRepository $recipes,
        private DeploymentManifestHasher $hasher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(string $target, ?string $profileName = null): array
    {
        $profile = $this->profiles->resolve($profileName);
        $identity = $this->identity->resolve();
        $variables = $this->environment->variables();
        $required = [];
        $secrets = [];

        foreach ($variables as $variable) {
            if ($variable->isRequired($profile->name, $profile->providerCodes)) {
                $required[] = $variable->key;
            }

            if ($variable->secret) {
                $secrets[] = $variable->key;
            }
        }

        sort($required);
        sort($secrets);
        $runtime = $this->runtime->describe();

        $recipe = $this->recipes->read();
        $manifest = [
            'schema' => self::Schema,
            'recipe' => [
                'schema' => $recipe['schema'],
                'version' => $recipe['version'],
                'target' => $recipe['target'],
                'hash' => $this->recipes->hash(),
            ],
            'application' => [
                ...$identity,
                'environment' => $target === 'local' ? 'local' : 'production',
            ],
            'package' => [
                'name' => '3neti/x-change',
                'locked_version' => InstalledVersions::isInstalled('3neti/x-change')
                    ? InstalledVersions::getPrettyVersion('3neti/x-change')
                    : 'source',
            ],
            'deployment' => [
                'target' => $target,
                'profile' => $profile->name,
                'connections' => $profile->connectionReferences,
            ],
            'environment' => [
                'source' => $target === 'local' ? 'local-file' : 'platform',
                'example_file' => '.env.example',
                'required' => $required,
                'secrets' => [
                    'provider' => $target === 'local'
                        ? 'local-environment'
                        : 'platform-secret-store',
                    'keys' => $secrets,
                    'permit_plaintext_repository_files' => false,
                ],
            ],
            'operations' => [
                'setup' => ['configure', 'preflight', 'install', 'verify'],
                'commission' => ['preflight', 'install', 'verify'],
                'deploy' => ['validate', 'platform-deploy', 'commission', 'verify'],
            ],
            'runtime' => [
                'queues' => $runtime['queues'],
                'scheduler_required' => true,
                'broadcasting_required' => $runtime['broadcasting_required'],
            ],
            'safety' => [
                'fail_closed' => true,
                'write_production_env' => false,
                'automatic_database_reset' => false,
                'automatic_provider_transfer' => false,
                'require_production_confirmation' => true,
            ],
        ];

        $manifest['manifest_hash'] = $this->hasher->hash($manifest);

        return $manifest;
    }
}
