<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Enums\CommissioningState;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Configuration\CommissioningConfigurationFingerprint;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use LBHurtado\XChange\Tests\Fakes\User;

it('fails closed when deployment configuration is incomplete', function (): void {
    config()->set('x-change.deployment.profile_explicitly_configured', false);

    $state = app(CommissioningStateResolver::class)->resolve();

    expect($state->state)->toBe(CommissioningState::ConfigurationRequired)
        ->and($state->missingVariables)->toContain('XCHANGE_DEPLOYMENT_PROFILE');
});

it('is ready to install before its manifest table exists', function (): void {
    Schema::dropIfExists('x_change_installation_manifests');

    $state = app(CommissioningStateResolver::class)->resolve();

    expect($state->state)->toBe(CommissioningState::ReadyToInstall);
});

it('becomes operational only with a matching installation manifest', function (): void {
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

    expect(app(CommissioningStateResolver::class)->resolve()->state)
        ->toBe(CommissioningState::Operational);

    config()->set('x-change.payout.system_user_id', 'changed@example.test');

    expect(app(CommissioningStateResolver::class)->resolve()->state)
        ->toBe(CommissioningState::InstallationIncomplete);
});

it('fails closed when a matching manifest has no persisted system principal Account', function (): void {
    config()->set('x-change.payout.system_user_column', 'email');
    config()->set('x-change.payout.system_user_id', 'missing-system@example.test');

    XChangeInstallationManifest::query()->create([
        'key' => CommissioningStateResolver::ManifestKey,
        'manifest_version' => CommissioningStateResolver::ManifestVersion,
        'package_version' => 'test',
        'profile' => 'development',
        'active_connection_references' => [],
        'configuration_fingerprint' => app(CommissioningConfigurationFingerprint::class)->current(),
        'completed_at' => now(),
    ]);

    $state = app(CommissioningStateResolver::class)->resolve();

    expect($state->state)->toBe(CommissioningState::InstallationIncomplete)
        ->and($state->reason)->toBe('system_principal_account_incomplete');
});

it('fails closed when the configured principal exists without its system Account', function (): void {
    config()->set('x-change.payout.system_user_column', 'email');
    config()->set('x-change.payout.system_user_id', 'system@example.test');

    User::query()->create([
        'name' => 'Unprovisioned System User',
        'email' => 'system@example.test',
        'password' => bcrypt('not-used'),
        'onboarding_meta' => [
            'system_principal' => [
                'authorization_reference' => 'test:unprovisioned',
                'interactive_login' => false,
            ],
        ],
    ]);

    XChangeInstallationManifest::query()->create([
        'key' => CommissioningStateResolver::ManifestKey,
        'manifest_version' => CommissioningStateResolver::ManifestVersion,
        'package_version' => 'test',
        'profile' => 'development',
        'active_connection_references' => [],
        'configuration_fingerprint' => app(CommissioningConfigurationFingerprint::class)->current(),
        'completed_at' => now(),
    ]);

    $state = app(CommissioningStateResolver::class)->resolve();

    expect($state->state)->toBe(CommissioningState::InstallationIncomplete)
        ->and($state->reason)->toBe('system_principal_account_incomplete');
});
