<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.lifecycle.defaults.system_user_email', 'system@example.test');
    config()->set('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph');
    config()->set('x-change.lifecycle.defaults.test_user_mobile', '09173011987');
    config()->set('x-change.onboarding.mobile_first_auth', true);
    config()->set('fortify.username', 'mobile');

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('runs the turnkey mobile boot scenario without voucher issuance', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'turnkey_mobile_boot',
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['scenario'])->toBe('turnkey_mobile_boot')
        ->and($payload['mode'])->toBe('turnkey_onboarding')
        ->and($payload['attempt_summary'])->toMatchArray([
            'failed' => 0,
            'total' => 5,
        ])
        ->and(data_get($payload, 'turnkey_checks.mobile_first_auth.passed'))->toBeTrue()
        ->and(data_get($payload, 'turnkey_checks.fortify_mobile_username.passed'))->toBeTrue()
        ->and(data_get($payload, 'turnkey_checks.user_mobile.passed'))->toBeTrue()
        ->and(data_get($payload, 'turnkey_checks.provider_topology.passed'))->toBeTrue()
        ->and(data_get($payload, 'generated'))->toBeNull();
});

it('runs the bank onboarding required scenario through the onboarding gateway seam', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'turnkey_bank_onboarding_required',
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['scenario'])->toBe('turnkey_bank_onboarding_required')
        ->and(data_get($payload, 'turnkey_checks.bank_onboarding_required.passed'))->toBeTrue()
        ->and(data_get($payload, 'turnkey_checks.bank_onboarding_required.actual.purpose'))->toBe('BankOnboardingRequired');
});

it('includes turnkey onboarding scenarios in a runnable group', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run-group', [
        'group' => 'turnkey-onboarding',
        '--json' => true,
        '--no-claim' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['group'])->toBe('turnkey-onboarding')
        ->and(array_keys($payload['results']))->toContain(
            'turnkey_mobile_boot',
            'turnkey_bank_onboarding_required',
            'turnkey_basic_cash_mobile',
        );
});
