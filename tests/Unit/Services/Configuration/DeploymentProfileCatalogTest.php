<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\DeploymentConnectionContributor;
use LBHurtado\EmiCore\Data\Configuration\ProviderConnectionTemplateData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\XChange\Services\Configuration\DeploymentConnectionCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentProfileCatalog;

it('resolves explicit provider profiles from contributed connections', function (): void {
    $catalog = profileCatalog([
        connectionContributor('netbank', 'netbank-primary'),
        connectionContributor('paynamics_constellation', 'paynamics-primary'),
    ]);

    expect($catalog->resolve('netbank')->connectionReferences)->toBe(['netbank-primary'])
        ->and($catalog->resolve('hybrid')->providerCodes)->toBe([
            'netbank',
            'paynamics_constellation',
        ]);
});

it('fails for unknown profiles and unavailable provider packages', function (): void {
    $catalog = profileCatalog([]);

    expect(fn () => $catalog->resolve('mystery'))->toThrow(RuntimeException::class, 'Unknown')
        ->and(fn () => $catalog->resolve('netbank'))->toThrow(RuntimeException::class, 'unavailable');
});

it('requires explicit custom connection references', function (): void {
    config()->set('x-change.deployment.custom_connections', []);

    expect(fn () => profileCatalog([])->resolve('custom'))
        ->toThrow(RuntimeException::class, 'XCHANGE_ACTIVE_CONNECTIONS');
});

it('forbids the development profile in production', function (): void {
    $environment = app()->environment();
    app()->instance('env', 'production');

    expect(fn () => profileCatalog([])->resolve('development'))
        ->toThrow(RuntimeException::class, 'forbidden');

    app()->instance('env', $environment);
});

/**
 * @param  list<DeploymentConnectionContributor>  $contributors
 */
function profileCatalog(array $contributors): DeploymentProfileCatalog
{
    return new DeploymentProfileCatalog(new DeploymentConnectionCatalog($contributors));
}

function connectionContributor(string $provider, string $reference): DeploymentConnectionContributor
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
