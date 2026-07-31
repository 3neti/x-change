<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\DeploymentConnectionContributor;
use LBHurtado\EmiCore\Data\Configuration\ProviderConnectionTemplateData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\XChange\Services\Configuration\DeploymentConnectionCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentProfileCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentTreasuryConnectionConfiguration;

it('activates only connections selected by the explicit profile', function (): void {
    config()->set('x-change.deployment.profile', 'netbank');
    config()->set('x-change.treasury.connections', []);
    $connections = new DeploymentConnectionCatalog([
        treasuryConnectionContributor('netbank', 'netbank-primary'),
        treasuryConnectionContributor('paynamics_constellation', 'paynamics-primary'),
    ]);

    $resolved = (new DeploymentTreasuryConnectionConfiguration(
        $connections,
        new DeploymentProfileCatalog($connections),
    ))->resolve();

    expect($resolved['netbank-primary']['mode'])->toBe('required')
        ->and($resolved['paynamics-primary']['mode'])->toBe('disabled');
});

function treasuryConnectionContributor(string $provider, string $reference): DeploymentConnectionContributor
{
    return new class($provider, $reference) implements DeploymentConnectionContributor
    {
        public function __construct(
            private readonly string $provider,
            private readonly string $reference,
        ) {}

        public function providerCode(): string
        {
            return $this->provider;
        }

        public function connectionTemplates(): array
        {
            return [new ProviderConnectionTemplateData(
                reference: $this->reference,
                provider: $this->provider,
                currency: 'PHP',
                inventoryReference: "inventory:{$this->provider}:php",
                settlementResourceReference: "resource:{$this->provider}:primary",
                settlementResourceType: 'provider_account',
                custodyMode: 'provider_projection',
                requiredCapabilities: [ProviderCapability::ReadinessProbe],
            )];
        }
    };
}
