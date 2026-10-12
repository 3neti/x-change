<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use LBHurtado\PaymentGateway\PaymentGatewayServiceProvider;
use LBHurtado\XChange\Enums\CommissioningState;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Configuration\CommissioningConfigurationFingerprint;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use LBHurtado\XChange\Services\Configuration\DeploymentConfigurationInspector;
use LBHurtado\XChange\Services\Configuration\DeploymentConnectionCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentEnvironmentCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentProfileCatalog;
use LBHurtado\XChange\Services\Treasury\SystemPrincipalProvisioningService;
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

it('keeps commissioning stable across runtime delivery changes', function (): void {
    $fingerprint = app(CommissioningConfigurationFingerprint::class);
    $original = $fingerprint->current();

    foreach ([
        'x-change.payment_feedback.sms.flows.authenticated_issuance',
        'x-change.public_auto_generate.turnstile.enabled',
        'x-change.campaigns.delivery.email.enabled',
        'queue.default',
        'services.resend.key',
        'engagespark.api_key',
    ] as $path) {
        $before = config($path);
        config()->set($path, $before === 'changed-for-test' ? 'another-test-value' : 'changed-for-test');

        expect($fingerprint->current())->toBe($original);

        config()->set($path, $before);
    }
});

it('changes commissioning fingerprint for trust identity changes', function (): void {
    $fingerprint = app(CommissioningConfigurationFingerprint::class);
    $original = $fingerprint->current();

    foreach ([
        'x-change.instance.id',
        'x-change.payout.system_user_id',
        'x-change.treasury.legal_entity_reference',
        'x-change.commercial.offerings.governance_mode',
    ] as $path) {
        $before = config($path);
        config()->set($path, 'changed-for-test');

        expect($fingerprint->current())->not->toBe($original);

        config()->set($path, $before);
    }
});

it('tracks active settlement accounts without tracking provider credentials', function (): void {
    $this->app->register(PaymentGatewayServiceProvider::class);

    foreach ([
        DeploymentConnectionCatalog::class,
        DeploymentEnvironmentCatalog::class,
        DeploymentProfileCatalog::class,
        DeploymentConfigurationInspector::class,
        CommissioningConfigurationFingerprint::class,
    ] as $service) {
        $this->app->forgetInstance($service);
    }

    config()->set('x-change.deployment.profile', 'netbank');

    $fingerprint = app(CommissioningConfigurationFingerprint::class);
    $original = $fingerprint->current();

    config()->set('disbursement.client.secret', 'rotated-credential');
    expect($fingerprint->current())->toBe($original);

    config()->set('disbursement.source.account_number', 'different-settlement-account');
    expect($fingerprint->current())->not->toBe($original);
});

it('fails closed for a manifest recorded by the interrupted commissioning contract', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    XChangeInstallationManifest::query()->create([
        'key' => CommissioningStateResolver::ManifestKey,
        'manifest_version' => 1,
        'package_version' => 'v1.0.95',
        'profile' => 'development',
        'active_connection_references' => [],
        'configuration_fingerprint' => app(CommissioningConfigurationFingerprint::class)->current(),
        'completed_at' => now(),
    ]);

    $state = app(CommissioningStateResolver::class)->resolve();

    expect($state->state)->toBe(CommissioningState::InstallationIncomplete)
        ->and($state->reason)->toBe('installation_manifest_stale');
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

it('fails closed when billable commissioning has no commercial principal Account', function (): void {
    config()->set('x-change.commercial.billing.mode', 'billable');
    config()->set('x-change.payout.system_user_column', 'email');
    config()->set('x-change.payout.system_user_id', 'system@example.test');

    app(SystemPrincipalProvisioningService::class)
        ->provision('test:system-only');

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
        ->and($state->reason)->toBe('commercial_principal_account_incomplete');
});
