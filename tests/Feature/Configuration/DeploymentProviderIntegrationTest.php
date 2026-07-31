<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\ConstellationServiceProvider;
use LBHurtado\PaymentGateway\PaymentGatewayServiceProvider;
use LBHurtado\XChange\Services\Configuration\DeploymentConnectionCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentEnvironmentCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentProfileCatalog;

it('discovers NetBank and Paynamics deployment contributors without x-change coupling', function (): void {
    $this->app->register(PaymentGatewayServiceProvider::class);
    $this->app->register(ConstellationServiceProvider::class);
    $this->app->forgetInstance(DeploymentConnectionCatalog::class);
    $this->app->forgetInstance(DeploymentEnvironmentCatalog::class);
    $this->app->forgetInstance(DeploymentProfileCatalog::class);

    $connections = app(DeploymentConnectionCatalog::class)->templates();
    $variables = collect(app(DeploymentEnvironmentCatalog::class)->variables());
    $profile = app(DeploymentProfileCatalog::class)->resolve('hybrid');

    expect($connections)->toHaveKeys(['netbank-primary', 'paynamics-primary'])
        ->and($variables->pluck('key'))->toContain(
            'NETBANK_FUNDING_CLIENT_SECRET',
            'CONSTELLATION_PASSWORD',
        )
        ->and($profile->providerCodes)->toBe([
            'netbank',
            'paynamics_constellation',
        ]);
});
