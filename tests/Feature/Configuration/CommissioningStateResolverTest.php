<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Enums\CommissioningState;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Configuration\CommissioningConfigurationFingerprint;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;

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
