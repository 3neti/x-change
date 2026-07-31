<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\EmiCore\Contracts\SettlementProviderRegistryContract;

final readonly class DeploymentConfigurationInspector
{
    public function __construct(
        private DeploymentEnvironmentCatalog $environment,
        private DeploymentConnectionCatalog $connections,
        private DeploymentProfileCatalog $profiles,
        private SettlementProviderRegistryContract $providers,
    ) {}

    /**
     * @return array{
     *     profile: string,
     *     active_connections: list<string>,
     *     active_providers: list<string>,
     *     installed_providers: list<string>,
     *     installed_but_disabled_providers: list<string>,
     *     missing_variables: list<string>,
     *     capability_readiness: array<string, array{ready: bool, missing: list<string>}>,
     *     legacy_published_config: bool,
     *     ready: bool
     * }
     */
    public function inspect(?string $profileName = null): array
    {
        $profile = $this->profiles->resolve($profileName);
        $installedProviders = array_values(array_unique(array_map(
            static fn ($template): string => $template->provider,
            $this->connections->templates(),
        )));
        sort($installedProviders);

        $missing = [];

        foreach ($this->environment->variables() as $variable) {
            if (! $variable->isRequired($profile->name, $profile->providerCodes)) {
                continue;
            }

            $value = $variable->key === 'XCHANGE_DEPLOYMENT_PROFILE'
                ? $profile->name
                : ($variable->configPath === null
                    ? null
                    : config($variable->configPath));

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $variable->key;
            }
        }

        sort($missing);
        $capabilityReadiness = [];
        $templates = $this->connections->templates();

        foreach ($profile->connectionReferences as $reference) {
            $template = $templates[$reference];
            $missingCapabilities = $this->providers->has($template->provider)
                ? array_values(array_map(
                    static fn ($capability): string => $capability->value,
                    array_filter(
                        $template->requiredCapabilities,
                        fn ($capability): bool => ! $this->providers->supports(
                            $template->provider,
                            $capability,
                        ),
                    ),
                ))
                : ['provider-not-registered'];
            $capabilityReadiness[$reference] = [
                'ready' => $missingCapabilities === [],
                'missing' => $missingCapabilities,
            ];
        }

        $capabilitiesReady = collect($capabilityReadiness)->every(
            static fn (array $readiness): bool => $readiness['ready'],
        );

        return [
            'profile' => $profile->name,
            'active_connections' => $profile->connectionReferences,
            'active_providers' => $profile->providerCodes,
            'installed_providers' => $installedProviders,
            'installed_but_disabled_providers' => array_values(array_diff(
                $installedProviders,
                $profile->providerCodes,
            )),
            'missing_variables' => $missing,
            'capability_readiness' => $capabilityReadiness,
            'legacy_published_config' => is_file(config_path('x-change.php')),
            'ready' => $missing === [] && $capabilitiesReady,
        ];
    }
}
