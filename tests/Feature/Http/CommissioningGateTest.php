<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Configuration\CommissioningConfigurationFingerprint;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;

beforeEach(function (): void {
    config()->set('x-change.commissioning.enabled', true);
    Route::middleware('web')->get('/host-home', fn (): string => 'host home');
    Route::middleware('api')->post('/api/host-webhook', fn (): array => ['accepted' => true]);
});

it('blocks ordinary html and api routes before commissioning', function (): void {
    $this->get('/host-home')
        ->assertServiceUnavailable()
        ->assertSee('X-Change is being commissioned')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    $this->postJson('/api/host-webhook')
        ->assertServiceUnavailable()
        ->assertJsonPath('state', 'installation_incomplete')
        ->assertJsonMissingPath('missing_variables');
});

it('exposes public commissioning and operational readiness safely', function (): void {
    $this->get('/x/commissioning')
        ->assertServiceUnavailable()
        ->assertSee('Financial operations locked')
        ->assertDontSee('XCHANGE_SYSTEM_USER_ID');

    $this->getJson('/x/ready')
        ->assertServiceUnavailable()
        ->assertJson(['ready' => false, 'state' => 'installation_incomplete']);
});

it('allows ordinary routes after a matching manifest exists', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    XChangeInstallationManifest::query()->create([
        'key' => CommissioningStateResolver::ManifestKey,
        'manifest_version' => CommissioningStateResolver::ManifestVersion,
        'package_version' => 'test',
        'profile' => 'development',
        'active_connection_references' => [],
        'configuration_fingerprint' => app(CommissioningConfigurationFingerprint::class)->current(),
        'completed_at' => now(),
    ]);

    $this->get('/host-home')->assertSuccessful()->assertSee('host home');
    $this->getJson('/x/ready')->assertSuccessful()->assertJson(['ready' => true]);
    $this->get('/x/commissioning')
        ->assertSuccessful()
        ->assertSee('X-Change is ready')
        ->assertSee('Run checks again')
        ->assertSee('Open Cockpit')
        ->assertDontSee('Operator access token');
});

it('blocks ordinary routes when the system principal disappears after commissioning', function (): void {
    $principal = provisionTestSystemPrincipalForCommissioning();

    XChangeInstallationManifest::query()->create([
        'key' => CommissioningStateResolver::ManifestKey,
        'manifest_version' => CommissioningStateResolver::ManifestVersion,
        'package_version' => 'test',
        'profile' => 'development',
        'active_connection_references' => [],
        'configuration_fingerprint' => app(CommissioningConfigurationFingerprint::class)->current(),
        'completed_at' => now(),
    ]);
    $principal->delete();

    $this->get('/host-home')->assertServiceUnavailable();
    $this->getJson('/x/ready')
        ->assertServiceUnavailable()
        ->assertJson([
            'ready' => false,
            'state' => 'installation_incomplete',
        ]);
});
