<?php

declare(strict_types=1);

use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Configuration\CommissioningConfigurationFingerprint;
use LBHurtado\XChange\Services\Configuration\CommissioningManifestRecorder;
use LBHurtado\XChange\Services\Configuration\CommissioningReleaseRefresher;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;

it('reports commissioning status without exposing configuration values', function (): void {
    $this->artisan('x-change:commissioning:status', ['--json' => true])
        ->expectsOutputToContain('installation_incomplete')
        ->assertFailed();
});

it('records a sanitized manifest idempotently', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $recorder = app(CommissioningManifestRecorder::class);

    $first = $recorder->record();
    $second = $recorder->record();

    expect($first->key)->toBe('primary')
        ->and($second->key)->toBe($first->key)
        ->and($second->configuration_fingerprint)->toHaveLength(64)
        ->and($second->active_connection_references)->toBe([])
        ->and($second->getAttributes())->not->toHaveKey('configuration');
});

it('invalidates an operational marker while manifest commissioning is incomplete', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $recorder = app(CommissioningManifestRecorder::class);
    $recorder->record();

    expect(app(CommissioningStateResolver::class)
        ->resolve()
        ->isOperational())->toBeTrue();

    $recorder->invalidate();

    expect(app(CommissioningStateResolver::class)
        ->resolve()
        ->isOperational())->toBeFalse();
});

it('refuses to record a manifest without the system principal Account', function (): void {
    app(CommissioningManifestRecorder::class)->record();
})->throws(
    RuntimeException::class,
    'Commissioning requires a persisted non-interactive system principal and Account.',
);

it('refuses adoption without explicit operator confirmation', function (): void {
    $this->artisan('x-change:commissioning:adopt')->assertFailed();
});

it('upgrades a matching legacy manifest and preserves its commissioning timestamp', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $completedAt = now()->subDay()->startOfSecond();
    $manifest = XChangeInstallationManifest::query()->create([
        'key' => CommissioningStateResolver::ManifestKey,
        'manifest_version' => 2,
        'package_version' => 'v1.0.129',
        'profile' => 'development',
        'active_connection_references' => [],
        'configuration_fingerprint' => app(CommissioningConfigurationFingerprint::class)->legacy(),
        'completed_at' => $completedAt,
    ]);

    expect(app(CommissioningReleaseRefresher::class)->refresh())->toBe('upgraded');

    $manifest->refresh();

    expect($manifest->manifest_version)->toBe(CommissioningStateResolver::ManifestVersion)
        ->and($manifest->configuration_fingerprint)->toBe(app(CommissioningConfigurationFingerprint::class)->current())
        ->and($manifest->package_version)->not->toBe('v1.0.129')
        ->and($manifest->completed_at->equalTo($completedAt))->toBeTrue()
        ->and(app(CommissioningStateResolver::class)->resolve()->isOperational())->toBeTrue()
        ->and(app(CommissioningReleaseRefresher::class)->refresh())->toBe('unchanged');
});

it('refreshes package metadata without recommissioning', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $manifest = app(CommissioningManifestRecorder::class)->record();
    $completedAt = $manifest->completed_at;
    $manifest->package_version = 'v1.0.129';
    $manifest->save();

    $this->artisan('x-change:commissioning:refresh-release')->assertSuccessful();

    $manifest->refresh();

    expect($manifest->package_version)->not->toBe('v1.0.129')
        ->and($manifest->completed_at->equalTo($completedAt))->toBeTrue();
});

it('refuses to upgrade a legacy manifest after configuration drift', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $manifest = XChangeInstallationManifest::query()->create([
        'key' => CommissioningStateResolver::ManifestKey,
        'manifest_version' => 2,
        'package_version' => 'v1.0.129',
        'profile' => 'development',
        'active_connection_references' => [],
        'configuration_fingerprint' => app(CommissioningConfigurationFingerprint::class)->legacy(),
        'completed_at' => now(),
    ]);

    config()->set('x-change.payment_feedback.sms.flows.authenticated_issuance', true);

    $this->artisan('x-change:commissioning:refresh-release')->assertFailed();

    expect($manifest->fresh()->manifest_version)->toBe(2);
});

it('refuses release refresh after commissioning identity drift', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $manifest = app(CommissioningManifestRecorder::class)->record();
    config()->set('x-change.instance.id', 'different-instance');

    $this->artisan('x-change:commissioning:refresh-release')->assertFailed();

    expect($manifest->fresh()->configuration_fingerprint)->toBe($manifest->configuration_fingerprint);
});

it('refuses release refresh when operational readiness fails', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $manifest = app(CommissioningManifestRecorder::class)->record();
    $manifest->package_version = 'v1.0.129';
    $manifest->save();
    config()->set('x-change.deployment.profile_explicitly_configured', false);

    $this->artisan('x-change:commissioning:refresh-release')->assertFailed();

    expect($manifest->fresh()->package_version)->toBe('v1.0.129');
});

it('allows the release hook before initial commissioning', function (): void {
    $this->artisan('x-change:commissioning:refresh-release')->assertSuccessful();
});
