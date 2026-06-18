<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.lifecycle.defaults.system_user_email', 'system@example.test');
    config()->set('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph');
    config()->set('x-change.lifecycle.defaults.test_user_mobile', '09173011987');
    config()->set('x-change.provider_runtime.lifecycle.allow_live_provider_scenarios', false);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('refuses live provider scenarios without explicit command opt in', function () {
    config()->set('x-change.provider_runtime.lifecycle.allow_live_provider_scenarios', true);

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'provider_paynamics_wallet_live_provision',
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($payload['scenario'])->toBe('provider_paynamics_wallet_live_provision')
        ->and($payload['mode'])->toBe('live_provider_verification')
        ->and($payload['message'])->toContain('--live-provider');
});

it('refuses live provider scenarios when runtime settings disable them', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'provider_paynamics_wallet_live_provision',
        '--live-provider' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($payload['scenario'])->toBe('provider_paynamics_wallet_live_provision')
        ->and($payload['mode'])->toBe('live_provider_verification')
        ->and($payload['message'])->toContain('runtime settings');
});

it('runs live provider verification when both safeguards are enabled', function () {
    config()->set('x-change.provider_runtime.lifecycle.allow_live_provider_scenarios', true);

    $manager = Mockery::mock(ProviderProvisioningManagerContract::class);
    $manager->shouldReceive('startOrResume')
        ->once()
        ->with(Mockery::type(User::class), Mockery::on(
            fn (array $payload): bool => data_get($payload, 'provider') === 'paynamics'
                && data_get($payload, 'mode') === ProviderProvisioningMode::WalletCreate->value
        ))
        ->andReturn([
            'provider' => 'paynamics',
            'topology' => 'paynamics',
            'mode' => ProviderProvisioningMode::WalletCreate->value,
            'status' => 'ready',
            'ready' => true,
            'link_id' => 123,
            'link' => [
                'provider_account_id' => 'acct-live-123',
                'provider_wallet_id' => 'wallet-live-123',
                'verification_status' => 'APPROVED',
                'metadata' => [
                    'raw' => [
                        'password' => 'not-for-output',
                    ],
                ],
            ],
        ]);

    app()->instance(ProviderProvisioningManagerContract::class, $manager);

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'provider_paynamics_wallet_live_provision',
        '--live-provider' => true,
        '--json' => true,
    ]);

    $output = Artisan::output();
    $payload = json_decode($output, true);

    expect($exitCode)->toBe(0)
        ->and($payload['scenario'])->toBe('provider_paynamics_wallet_live_provision')
        ->and($payload['mode'])->toBe('live_provider_verification')
        ->and($payload['provider_verification'])->toMatchArray([
            'provider' => 'paynamics',
            'mode' => ProviderProvisioningMode::WalletCreate->value,
            'status' => 'ready',
            'ready' => true,
            'link_id' => 123,
            'provider_account_id' => 'acct-live-123',
            'provider_wallet_id' => 'wallet-live-123',
            'verification_status' => 'APPROVED',
        ])
        ->and($output)->not->toContain('not-for-output')
        ->and($output)->not->toContain('password');
});

it('registers the live provider scenario group', function () {
    $group = config('x-change.lifecycle.scenario_groups.live-provider.scenarios');

    expect($group)->toContain(
        'provider_paynamics_wallet_live_provision',
        'provider_paynamics_bank_account_live_link',
        'provider_netbank_source_account_live_readiness',
    );
});
