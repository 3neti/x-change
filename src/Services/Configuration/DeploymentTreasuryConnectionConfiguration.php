<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\EmiCore\Data\Configuration\ProviderConnectionTemplateData;

final readonly class DeploymentTreasuryConnectionConfiguration
{
    public function __construct(
        private DeploymentConnectionCatalog $connections,
        private DeploymentProfileCatalog $profiles,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function resolve(): array
    {
        $profile = $this->profiles->resolve();
        $configured = (array) config('x-change.treasury.connections', []);
        $connections = [];
        $templates = $this->connections->templates();

        foreach ($templates as $reference => $template) {
            $connections[$reference] = array_replace_recursive(
                $this->toConfiguration($template),
                (array) ($configured[$reference] ?? []),
            );
        }

        foreach ($configured as $reference => $configuration) {
            if (! isset($connections[$reference]) && is_array($configuration)) {
                $connections[(string) $reference] = $configuration;
            }
        }

        foreach ($connections as $reference => &$connection) {
            if (! isset($templates[$reference])) {
                continue;
            }

            $connection['mode'] = in_array(
                $reference,
                $profile->connectionReferences,
                true,
            ) ? 'required' : 'disabled';
        }

        return $connections;
    }

    /**
     * @return array<string, mixed>
     */
    private function toConfiguration(ProviderConnectionTemplateData $template): array
    {
        return [
            'provider' => $template->provider,
            'mode' => 'disabled',
            'currency' => $template->currency,
            'decimal_places' => $template->decimalPlaces,
            'inventory_reference' => $template->inventoryReference,
            'settlement_resource_reference' => $template->settlementResourceReference,
            'settlement_resource_type' => $template->settlementResourceType,
            'custody_mode' => $template->custodyMode,
            'required_capabilities' => array_map(
                static fn ($capability): string => $capability->value,
                $template->requiredCapabilities,
            ),
        ];
    }
}
