<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Contracts\Publication\XChangePublicationContributor;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Publication\PublicationCatalog;

it('reports x-change doctor checks as json', function () {
    $this->artisan('x-change:doctor --json')
        ->assertExitCode(0);
});

it('runs a strict pre-install doctor without requiring post-install tables', function () {
    $exitCode = Artisan::call('x-change:doctor', [
        '--pre-install' => true,
        '--strict' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['success'])->toBeTrue()
        ->and(collect($payload['checks'])->pluck('name'))
        ->not->toContain('onboarding sessions table');
    expect(collect($payload['checks'])->pluck('name'))
        ->not->toContain('system principal account');
});

it('fails strict runtime readiness without the persisted system principal Account', function (): void {
    config()->set('x-change.payout.system_user_column', 'email');
    config()->set('x-change.payout.system_user_id', 'missing-system@example.test');

    $exitCode = Artisan::call('x-change:doctor', [
        '--strict' => true,
        '--json' => true,
    ]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'system principal account');

    expect($exitCode)->toBe(1)
        ->and($check['passed'])->toBeFalse()
        ->and($check['meta'])->toBe([
            'principal_persisted' => false,
            'system_designation_present' => false,
            'account_ready' => false,
        ]);
});

it('requires an explicitly configured deployment profile', function () {
    config()->set('x-change.deployment.profile_explicitly_configured', false);

    $exitCode = Artisan::call('x-change:doctor', [
        '--pre-install' => true,
        '--strict' => true,
        '--json' => true,
    ]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'deployment configuration');

    expect($exitCode)->toBe(1)
        ->and($check['passed'])->toBeFalse()
        ->and($check['meta']['missing_variables'])
        ->toContain('XCHANGE_DEPLOYMENT_PROFILE');
});

it('reports invalid live system principal identity variables', function () {
    config()->set('x-change.deployment.profile', 'netbank');
    config()->set('x-change.payout.system_user_column', 'id');
    config()->set('x-change.payout.system_user_id', '1');

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'system principal identity');

    expect($check['passed'])->toBeFalse()
        ->and($check['meta']['missing_variables'])->toBe([
            'XCHANGE_SYSTEM_USER_COLUMN',
            'XCHANGE_SYSTEM_USER_ID',
        ]);
});

it('fails closed when enabled campaign email delivery is incomplete', function () {
    config()->set('x-change.campaigns.delivery.email.enabled', true);
    config()->set('mail.default', 'log');
    config()->set('mail.from.address', 'hello@example.com');

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'campaign email delivery');

    expect($check['passed'])->toBeFalse()
        ->and($check['meta']['missing_variables'])->toBe([
            'MAIL_MAILER',
            'MAIL_FROM_ADDRESS',
        ]);
});

it('fails closed when enabled EngageSpark SMS delivery has no credentials', function () {
    config()->set('x-change.campaigns.delivery.sms.enabled', true);
    config()->set('x-feedback.transports.sms.driver', 'engagespark');
    config()->set('x-feedback.transports.sms.sender', 'XCHANGE');
    config()->set('engagespark.api_key', null);
    config()->set('engagespark.org_id', null);
    config()->set('engagespark.sender_id', null);

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'SMS delivery');

    expect($check['passed'])->toBeFalse()
        ->and($check['meta']['missing_variables'])->toBe([
            'ENGAGESPARK_API_KEY',
            'ENGAGESPARK_ORGANIZATION_ID',
            'ENGAGESPARK_SENDER_ID',
        ]);
});

it('does not require credentials for disabled campaign delivery channels', function () {
    config()->set('x-change.campaigns.delivery.email.enabled', false);
    config()->set('x-change.campaigns.delivery.sms.enabled', false);

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $checks = collect(json_decode(Artisan::output(), true)['checks']);

    expect($checks->firstWhere('name', 'campaign email delivery')['passed'])->toBeTrue()
        ->and($checks->firstWhere('name', 'SMS delivery')['passed'])->toBeTrue();
});

it('reports unavailable optional instruction services without failing strict readiness', function (): void {
    config()->set('x-change.instruction_capabilities.required', []);
    config()->set('location-handler.opencage_api_key', null);
    config()->set('location-handler.map_provider', 'mapbox');
    config()->set('location-handler.mapbox_token', null);

    $exitCode = Artisan::call('x-change:doctor', [
        '--pre-install' => true,
        '--strict' => true,
        '--json' => true,
    ]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'instruction services');

    expect($exitCode)->toBe(0)
        ->and($check['passed'])->toBeTrue()
        ->and($check['meta']['unavailable'])->toContain('location')
        ->and($check['meta']['missing_variables'])->toBe([]);
});

it('fails strict readiness when a required instruction service is unavailable', function (): void {
    config()->set('x-change.instruction_capabilities.required', ['location']);
    config()->set('location-handler.opencage_api_key', null);
    config()->set('location-handler.map_provider', 'mapbox');
    config()->set('location-handler.mapbox_token', null);

    $exitCode = Artisan::call('x-change:doctor', [
        '--pre-install' => true,
        '--strict' => true,
        '--json' => true,
    ]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'instruction services');

    expect($exitCode)->toBe(1)
        ->and($check['passed'])->toBeFalse()
        ->and($check['meta']['missing_variables'])->toBe([
            'MAPBOX_TOKEN',
            'OPENCAGE_API_KEY',
        ]);
});

it('reports published cockpit asset drift as json', function () {
    $exitCode = Artisan::call('x-change:doctor', [
        '--assets' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['checks'][0]['name'])->toBe('generated build inputs')
        ->and($payload['checks'][0]['meta'])->toHaveKeys(['summary', 'resources', 'files']);
});

it('keeps ordinary diagnostics non-blocking while reporting failed readiness', function () {
    app()->instance(PublicationCatalog::class, failingBuildPublicationCatalog());

    $exitCode = Artisan::call('x-change:doctor', [
        '--assets' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('x-change.readiness-report.v1')
        ->and($payload['success'])->toBeFalse()
        ->and($payload['strict'])->toBeFalse()
        ->and($payload['summary'])->toBe([
            'passed' => 0,
            'failed' => 1,
        ]);
});

it('blocks deployment in strict mode when any readiness check fails', function () {
    app()->instance(PublicationCatalog::class, failingBuildPublicationCatalog());

    $exitCode = Artisan::call('x-change:doctor', [
        '--assets' => true,
        '--strict' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['strict'])->toBeTrue()
        ->and($payload['checks'][0]['passed'])->toBeFalse();
});

it('reports unsafe synchronous queues and local scheduler locks', function () {
    config()->set('x-change.deployment.profile', 'netbank');
    config()->set('queue.default', 'sync');
    config()->set('cache.default', 'array');

    Artisan::call('x-change:doctor', ['--json' => true]);

    $checks = collect(json_decode(Artisan::output(), true)['checks']);
    $queue = $checks->firstWhere('name', 'durable queue runtime');
    $cache = $checks->firstWhere('name', 'shared scheduler lock cache');

    expect($queue['passed'])->toBeFalse()
        ->and($queue['meta']['required_queues'])->toBe([
            'default',
            'x-change-feedback',
            'x-change-funding',
        ])
        ->and($cache['passed'])->toBeFalse();
});

it('accepts durable queues and a shared scheduler lock cache', function () {
    config()->set('queue.default', 'database');
    config()->set('cache.default', 'database');

    Artisan::call('x-change:doctor', ['--json' => true]);

    $checks = collect(json_decode(Artisan::output(), true)['checks']);

    expect($checks->firstWhere('name', 'durable queue runtime')['passed'])->toBeTrue()
        ->and($checks->firstWhere('name', 'shared scheduler lock cache')['passed'])->toBeTrue();
});

it('accepts private local claim evidence storage for a local netbank runtime', function (): void {
    config()->set('x-change.deployment.profile', 'netbank');
    config()->set('x-change.deployment.runtime_tier', 'local');
    config()->set('x-change.claim.evidence.disk', 'local');
    config()->set('filesystems.disks.local.driver', 'local');

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'claim evidence storage');
    expect($check['passed'])->toBeTrue()
        ->and($check['meta']['runtime_tier'])->toBe('local')
        ->and($check['meta']['disk'])->toBe('local')
        ->and($check['meta']['private'])->toBeTrue()
        ->and($check['meta']['durable'])->toBeFalse()
        ->and($check['meta']['missing_variables'])->toBe([]);
});

it('rejects local claim evidence storage for staging and production runtimes', function (string $tier): void {
    config()->set('x-change.deployment.profile', 'netbank');
    config()->set('x-change.deployment.runtime_tier', $tier);
    config()->set('x-change.claim.evidence.disk', 'local');
    config()->set('filesystems.disks.local.driver', 'local');

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'claim evidence storage');

    expect($check['passed'])->toBeFalse()
        ->and($check['meta']['runtime_tier'])->toBe($tier)
        ->and($check['meta']['missing_variables'])->toBe([
            'XCHANGE_CLAIM_EVIDENCE_DISK',
        ]);
})->with(['staging', 'production']);

it('accepts a configured durable private claim evidence disk for staging', function (): void {
    config()->set('x-change.deployment.profile', 'netbank');
    config()->set('x-change.deployment.runtime_tier', 'staging');
    config()->set('x-change.claim.evidence.disk', 's3');
    config()->set('filesystems.disks.s3', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'bucket' => 'test-bucket',
    ]);

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'claim evidence storage');

    expect($check['passed'])->toBeTrue()
        ->and($check['meta']['runtime_tier'])->toBe('staging')
        ->and($check['meta']['disk'])->toBe('s3')
        ->and($check['meta']['driver'])->toBe('s3')
        ->and($check['meta']['durable'])->toBeTrue();
});

it('reports missing s3 credentials only when a durable runtime selects s3', function (): void {
    config()->set('x-change.deployment.profile', 'netbank');
    config()->set('x-change.deployment.runtime_tier', 'production');
    config()->set('x-change.claim.evidence.disk', 's3');
    config()->set('filesystems.disks.s3', [
        'driver' => 's3',
        'key' => null,
        'secret' => null,
        'bucket' => null,
    ]);

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'claim evidence storage');

    expect($check['passed'])->toBeFalse()
        ->and($check['meta']['missing_variables'])->toBe([
            'AWS_ACCESS_KEY_ID',
            'AWS_BUCKET',
            'AWS_SECRET_ACCESS_KEY',
        ]);
});

it('fails closed with a useful diagnostic for an unknown runtime tier', function (): void {
    config()->set('x-change.deployment.runtime_tier', 'preview');

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $payload = json_decode(Artisan::output(), true);
    $check = collect($payload['checks'])->firstWhere('name', 'claim evidence storage');

    expect($payload['success'])->toBeFalse()
        ->and($check['passed'])->toBeFalse()
        ->and($check['message'])->toContain('Unknown X-Change runtime tier [preview]')
        ->and($check['meta']['missing_variables'])->toBe(['XCHANGE_RUNTIME_TIER']);
});

it('rejects an unavailable identity OTP gateway in production', function () {
    config()->set('app.env', 'production');
    config()->set('x-change.onboarding.mobile_verification.enabled', true);
    config()->set('x-change.onboarding.voucher.require_otp', true);
    config()->set('x-change.onboarding.voucher.require_pin_setup', false);
    config()->set('x-change.onboarding.identity_otp.driver', 'unavailable');
    config()->set('x-change.onboarding.identity_otp.token', null);
    config()->set('x-change.onboarding.identity_otp.base_url', 'http://txtcmdr.test');

    Artisan::call('x-change:doctor', ['--json' => true]);

    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'production onboarding OTP');

    expect($check['passed'])->toBeFalse()
        ->and($check['meta']['driver'])->toBe('unavailable')
        ->and($check['meta']['token_configured'])->toBeFalse()
        ->and($check['meta']['secure_endpoint'])->toBeFalse();
    expect($check['meta']['pin_setup_required'])->toBeFalse();
});

it('accepts a configured production onboarding OTP driver', function () {
    config()->set('app.env', 'production');
    config()->set('x-change.onboarding.mobile_verification.enabled', true);
    config()->set('x-change.onboarding.voucher.require_otp', true);
    config()->set('x-change.onboarding.voucher.require_pin_setup', true);
    config()->set('x-change.onboarding.identity_otp.driver', 'txtcmdr');
    config()->set('x-change.onboarding.identity_otp.token', 'test-token');
    config()->set('x-change.onboarding.identity_otp.base_url', 'https://txtcmdr.example.test');

    Artisan::call('x-change:doctor', ['--json' => true]);

    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'production onboarding OTP');

    expect($check['passed'])->toBeTrue()
        ->and($check['meta']['driver'])->toBe('txtcmdr')
        ->and($check['meta']['token_configured'])->toBeTrue()
        ->and($check['meta']['secure_endpoint'])->toBeTrue();
});

it('rejects unsafe application settings in production', function () {
    config()->set('app.env', 'production');
    config()->set('app.debug', true);
    config()->set('app.key', null);
    config()->set('app.url', 'http://example.test');
    config()->set('session.secure', false);

    Artisan::call('x-change:doctor', ['--json' => true]);

    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'production application security');

    expect($check['passed'])->toBeFalse()
        ->and($check['meta'])->toMatchArray([
            'environment' => 'production',
            'debug' => true,
            'app_key_configured' => false,
            'https' => false,
            'secure_cookies' => false,
        ])
        ->and($check['meta']['missing_variables'])->toBe([
            'APP_DEBUG',
            'APP_KEY',
            'APP_URL',
            'SESSION_SECURE_COOKIE',
        ]);
});

it('accepts hardened application settings in production', function () {
    config()->set('app.env', 'production');
    config()->set('app.debug', false);
    config()->set('app.key', 'base64:stable-production-key');
    config()->set('app.url', 'https://x-change.example');
    config()->set('session.secure', true);

    Artisan::call('x-change:doctor', ['--json' => true]);

    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'production application security');

    expect($check['passed'])->toBeTrue();
});

it('reports the cockpit operator activity runtime profile as an explicit doctor check', function () {
    $exitCode = Artisan::call('x-change:doctor', [
        '--operator-activity-runtime' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['checks'])->toHaveCount(1)
        ->and($payload['checks'][0]['name'])->toBe('cockpit operator activity runtime profile')
        ->and($payload['checks'][0]['passed'])->toBeTrue()
        ->and($payload['checks'][0]['meta']['schema'])->toBe('x-change.cockpit.operator-issuance-activity-runtime-profile.v1')
        ->and($payload['checks'][0]['meta']['status'])->toBe('not_wired')
        ->and($payload['checks'][0]['meta']['safety']['defaults_safe'])->toBeTrue();
});

it('reports explicitly enabled cockpit operator activity runtime components through doctor', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');

    $exitCode = Artisan::call('x-change:doctor', [
        '--operator-activity-runtime' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);
    $repository = collect($payload['checks'][0]['meta']['components'])->firstWhere('key', 'repository');

    expect($exitCode)->toBe(0)
        ->and($payload['checks'])->toHaveCount(1)
        ->and($payload['checks'][0]['meta']['status'])->toBe('partially_wired')
        ->and($payload['checks'][0]['meta']['repository_enabled'])->toBeTrue()
        ->and($repository['resolved_class'])->toBe(DatabaseCockpitOperatorIssuanceActivityRepository::class);
});

it('fails closed when Partner API OAuth signing keys are unavailable', function () {
    config()->set('x-change.partner_api.enabled', true);
    config()->set('passport.private_key', null);
    config()->set('passport.public_key', null);

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'partner api oauth');

    expect($check['passed'])->toBeFalse()
        ->and($check['meta']['missing_variables'])->toBe([
            'PASSPORT_PRIVATE_KEY',
            'PASSPORT_PUBLIC_KEY',
        ]);
});

it('does not require OAuth signing keys while Partner API operations are disabled', function () {
    config()->set('x-change.partner_api.enabled', false);
    config()->set('passport.private_key', null);
    config()->set('passport.public_key', null);

    Artisan::call('x-change:doctor', ['--pre-install' => true, '--json' => true]);
    $check = collect(json_decode(Artisan::output(), true)['checks'])
        ->firstWhere('name', 'partner api oauth');

    expect($check['passed'])->toBeTrue()
        ->and($check['meta']['missing_variables'])->toBe([])
        ->and($check['meta']['public_discovery_enabled'])->toBeTrue();
});

function failingBuildPublicationCatalog(): PublicationCatalog
{
    return new PublicationCatalog([
        new class implements XChangePublicationContributor
        {
            public function publications(): iterable
            {
                yield new PublicationDefinitionData(
                    id: 'missing.generated',
                    owner: '3neti/missing',
                    scope: PublicationScope::Build,
                    invocation: PublicationInvocation::Tag,
                    target: 'missing-generated-build-inputs',
                    overwritePolicy: PublicationOverwritePolicy::AlwaysGenerated,
                    description: 'Missing generated input.',
                    available: false,
                    generated: true,
                );
            }
        },
    ]);
}
