<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\EmiCore\Contracts\SettlementProviderRegistryContract;
use LBHurtado\PaymentGateway\PaymentGatewayServiceProvider;
use LBHurtado\XChange\Services\Configuration\DeploymentConfigurationInspector;
use LBHurtado\XChange\Services\Configuration\DeploymentConnectionCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentEnvironmentCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentProfileCatalog;
use LBHurtado\XChange\Services\Configuration\ManagedEnvironmentExampleRenderer;

beforeEach(function (): void {
    $this->app->register(PaymentGatewayServiceProvider::class);

    foreach ([
        DeploymentConfigurationInspector::class,
        DeploymentConnectionCatalog::class,
        DeploymentEnvironmentCatalog::class,
        DeploymentProfileCatalog::class,
        SettlementProviderRegistryContract::class,
    ] as $service) {
        $this->app->forgetInstance($service);
    }
});

it('reports installed but disabled providers for development', function (): void {
    Artisan::call('x-change:configuration:inspect', ['--json' => true]);
    $payload = json_decode(Artisan::output(), true);

    expect($payload['ready'])->toBeTrue()
        ->and($payload['profile'])->toBe('development')
        ->and($payload['active_connections'])->toBe([])
        ->and($payload['installed_but_disabled_providers'])->toContain('netbank');
});

it('fails closed for incomplete live provider configuration', function (): void {
    $exitCode = Artisan::call('x-change:configuration:inspect', [
        '--profile' => 'netbank',
        '--strict' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($payload['ready'])->toBeFalse()
        ->and($payload['missing_variables'])->toContain(
            'NETBANK_FUNDING_CLIENT_SECRET',
            'XCHANGE_TREASURY_LEGAL_ENTITY_REFERENCE',
        );
});

it('updates only the managed environment example section', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-env-');
    file_put_contents($path, "APP_NAME=Host\nHOST_SETTING=preserved\n");

    try {
        $exitCode = Artisan::call('x-change:configure', [
            '--profile' => 'netbank',
            '--path' => $path,
            '--json' => true,
        ]);
        $first = file_get_contents($path);
        Artisan::call('x-change:configure', [
            '--profile' => 'netbank',
            '--path' => $path,
            '--json' => true,
        ]);
        $second = file_get_contents($path);

        expect($exitCode)->toBe(0)
            ->and($second)->toBe($first)
            ->and($second)->toContain('HOST_SETTING=preserved')
            ->toContain('XCHANGE_DEPLOYMENT_PROFILE=netbank')
            ->toContain('NETBANK_FUNDING_CLIENT_SECRET=')
            ->and(substr_count($second, ManagedEnvironmentExampleRenderer::BeginMarker))->toBe(1);
    } finally {
        @unlink($path);
    }
});
