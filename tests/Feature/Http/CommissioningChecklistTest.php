<?php

declare(strict_types=1);
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Configuration\CommissioningManifestRecorder;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
});

afterEach(function (): void {
    app()->instance('env', 'testing');
});

it('protects the detailed commissioning checklist with a rotated session-bound token', function (): void {
    config()->set('x-change.commissioning.enabled', true);
    config()->set('x-change.commissioning.access_token', 'commissioning-secret-one');

    $this->get('/x/commissioning/checklist')->assertNotFound();

    $this->post('/x/commissioning/checklist', [
        'access_token' => 'wrong-secret',
    ])->assertNotFound();

    $this->post('/x/commissioning/checklist', [
        'access_token' => 'commissioning-secret-one',
    ])->assertRedirect('/x/commissioning/checklist');

    $this->get('/x/commissioning/checklist')
        ->assertSuccessful()
        ->assertSee('Commission X-Change')
        ->assertSee('Deployment Configuration')
        ->assertSee('Runtime processes')
        ->assertSee('x-change-funding, x-change-feedback, default')
        ->assertSee('php artisan queue:work database --queue=x-change-funding,x-change-feedback,default --sleep=3 --timeout=60')
        ->assertSee('php artisan schedule:work')
        ->assertSee('Run checks again')
        ->assertSee('System Principal Account')
        ->assertSee('Action needed')
        ->assertSee('Complete the System Account')
        ->assertSee('--provision-system-principal')
        ->assertSee('generated and reused automatically')
        ->assertDontSee('--system-principal-authorization-reference')
        ->assertSee('php artisan x-change:doctor --strict --no-interaction')
        ->assertDontSee('--fresh-database')
        ->assertDontSee('Open Cockpit');

    config()->set('x-change.commissioning.access_token', 'commissioning-secret-two');

    $this->get('/x/commissioning/checklist')->assertNotFound();
});

it('allows the fixed fallback token only for an unconfigured local application', function (): void {
    app()->instance('env', 'local');
    config()->set('x-change.commissioning.enabled', true);
    config()->set('x-change.commissioning.access_token');

    $this->withSession(['_token' => 'commissioning-test-csrf'])
        ->post('/x/commissioning/checklist', [
            '_token' => 'commissioning-test-csrf',
            'access_token' => '317537',
        ])->assertRedirect('/x/commissioning/checklist');

    $this->get('/x/commissioning/checklist')
        ->assertSuccessful()
        ->assertSee('Commission X-Change');
});

it('uses an explicit local token instead of the fixed fallback', function (): void {
    app()->instance('env', 'local');
    config()->set('x-change.commissioning.enabled', true);
    config()->set('x-change.commissioning.access_token', 'explicit-local-secret');

    $this->withSession(['_token' => 'commissioning-test-csrf'])
        ->post('/x/commissioning/checklist', [
            '_token' => 'commissioning-test-csrf',
            'access_token' => '317537',
        ])->assertNotFound();

    $this->post('/x/commissioning/checklist', [
        '_token' => 'commissioning-test-csrf',
        'access_token' => 'explicit-local-secret',
    ])->assertRedirect('/x/commissioning/checklist');
});

it('invalidates a local fallback session when an explicit token is configured', function (): void {
    app()->instance('env', 'local');
    config()->set('x-change.commissioning.enabled', true);
    config()->set('x-change.commissioning.access_token');

    $this->withSession(['_token' => 'commissioning-test-csrf'])
        ->post('/x/commissioning/checklist', [
            '_token' => 'commissioning-test-csrf',
            'access_token' => '317537',
        ])->assertRedirect('/x/commissioning/checklist');

    config()->set('x-change.commissioning.access_token', 'replacement-secret');

    $this->get('/x/commissioning/checklist')->assertNotFound();
});

it('rejects the fixed fallback token outside the local environment', function (string $environment): void {
    app()->instance('env', $environment);
    config()->set('x-change.commissioning.enabled', true);
    config()->set('x-change.commissioning.access_token');

    $this->withSession(['_token' => 'commissioning-test-csrf'])
        ->post('/x/commissioning/checklist', [
            '_token' => 'commissioning-test-csrf',
            'access_token' => '317537',
        ])->assertNotFound();

    $this->get('/x/commissioning/checklist')->assertNotFound();
})->with(['production', 'staging', 'testing', 'development']);

it('hides the recovery directive after the system Account is commissioned', function (): void {
    config()->set('x-change.commissioning.enabled', true);
    config()->set('x-change.commissioning.access_token', 'commissioning-secret');
    provisionTestSystemPrincipalForCommissioning();
    $manifest = app(CommissioningManifestRecorder::class)->record();
    app(ProvisionCommercialBaselines::class)->provision(
        'installation-manifest:'.$manifest->configuration_fingerprint,
    );

    $this->post('/x/commissioning/checklist', [
        'access_token' => 'commissioning-secret',
    ])->assertRedirect('/x/commissioning/checklist');

    $this->get('/x/commissioning/checklist')
        ->assertSuccessful()
        ->assertSee('Open Cockpit')
        ->assertSee('Installation Manifest')
        ->assertSee('Commercial Governance')
        ->assertSee('Bootstrap Immutable')
        ->assertSee('Baseline Active Changes Locked')
        ->assertSee('Changes locked')
        ->assertSee('Agreement Economics')
        ->assertSee('2 / 2 active')
        ->assertSee('Pay Code')
        ->assertSee('Account Funding')
        ->assertSee('Partners and settlement operations')
        ->assertSee('Partner registry')
        ->assertSee('Provider payout calls')
        ->assertSee('x-change-funding')
        ->assertSee('Authority provisioning')
        ->assertSee('Invitation delivery')
        ->assertSee('x-change-feedback ready')
        ->assertSee('Production API ceremony')
        ->assertSee('recorded installation matches the active deployment configuration')
        ->assertDontSee('Complete the System Account')
        ->assertDontSee('Confirm the Updated Configuration')
        ->assertDontSee('--provision-system-principal');
});

it('shows a stale installation manifest as the missing readiness action', function (): void {
    config()->set('x-change.commissioning.enabled', true);
    config()->set('x-change.commissioning.access_token', 'commissioning-secret');
    provisionTestSystemPrincipalForCommissioning();

    $manifest = app(CommissioningManifestRecorder::class)->record();
    $manifest->forceFill([
        'configuration_fingerprint' => str_repeat('0', 64),
    ])->save();

    $this->post('/x/commissioning/checklist', [
        'access_token' => 'commissioning-secret',
    ])->assertRedirect('/x/commissioning/checklist');

    $this->get('/x/commissioning/checklist')
        ->assertSuccessful()
        ->assertSee('Action needed')
        ->assertSee('Installation Manifest')
        ->assertSee('recorded installation does not match the active deployment configuration')
        ->assertSee('Confirm the Updated Configuration')
        ->assertSee('php artisan x-change:commissioning:adopt --confirm-existing-installation --no-interaction')
        ->assertDontSee('Supply the missing deployment secrets');
});
