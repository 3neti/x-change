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
        ->and($payload['runtime_tier'])->toBe('local')
        ->and($payload['active_connections'])->toBe([])
        ->and($payload['installed_but_disabled_providers'])->toContain('netbank');
});

it('fails closed for incomplete live provider configuration', function (): void {
    $exitCode = Artisan::call('x-change:configuration:inspect', [
        '--profile' => 'netbank',
        '--runtime-tier' => 'local',
        '--strict' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($payload['ready'])->toBeFalse()
        ->and($payload['missing_variables'])->toContain(
            'NETBANK_CLIENT_SECRET',
            'NETBANK_DISBURSEMENT_ENDPOINT',
            'XCHANGE_TREASURY_LEGAL_ENTITY_REFERENCE',
        )
        ->and($payload['missing_variables'])->not->toContain(
            'AWS_ACCESS_KEY_ID',
            'AWS_BUCKET',
            'AWS_SECRET_ACCESS_KEY',
        );
});

it('updates only the managed environment example section', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-env-');
    file_put_contents($path, "APP_NAME=Host\nHOST_SETTING=preserved\n");

    try {
        $exitCode = Artisan::call('x-change:configure', [
            '--profile' => 'netbank',
            '--runtime-tier' => 'staging',
            '--path' => $path,
            '--json' => true,
        ]);
        $first = file_get_contents($path);
        Artisan::call('x-change:configure', [
            '--profile' => 'netbank',
            '--runtime-tier' => 'staging',
            '--path' => $path,
            '--json' => true,
        ]);
        $second = file_get_contents($path);

        expect($exitCode)->toBe(0)
            ->and($second)->toBe($first)
            ->and($second)->toContain('HOST_SETTING=preserved')
            ->toContain('XCHANGE_DEPLOYMENT_PROFILE=netbank')
            ->toContain('XCHANGE_RUNTIME_TIER=staging')
            ->toContain('XCHANGE_CLAIM_EVIDENCE_DISK=s3')
            ->toContain('XCHANGE_SYSTEM_USER_COLUMN=email')
            ->toContain('NETBANK_FUNDING_CLIENT_SECRET=')
            ->and(substr_count($second, ManagedEnvironmentExampleRenderer::BeginMarker))->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('rejects an unknown runtime tier without changing the environment example', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-env-');
    file_put_contents($path, "APP_NAME=Host\n");

    try {
        $exitCode = Artisan::call('x-change:configure', [
            '--profile' => 'development',
            '--runtime-tier' => 'preview',
            '--path' => $path,
            '--json' => true,
        ]);

        expect($exitCode)->toBe(1)
            ->and(file_get_contents($path))->toBe("APP_NAME=Host\n");
    } finally {
        @unlink($path);
    }
});
