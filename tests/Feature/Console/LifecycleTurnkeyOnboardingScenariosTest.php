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
            'turnkey_provider_link_ready',
            'turnkey_provider_link_pending_blocks',
            'turnkey_netbank_bank_account_ready',
            'turnkey_paynamics_wallet_fake_provisioned',
            'turnkey_issuer_blocks_missing_provider_wallet',
            'turnkey_issuer_allows_ready_provider_wallet',
            'turnkey_claim_blocks_missing_bank_account',
            'turnkey_claim_resumes_after_provider_account_ready',
            'turnkey_basic_cash_mobile',
        );
});

it('runs the provider account link readiness scenario', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'turnkey_provider_link_ready',
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['scenario'])->toBe('turnkey_provider_link_ready')
        ->and(data_get($payload, 'turnkey_checks.provider_runtime_settings.passed'))->toBeTrue()
        ->and(data_get($payload, 'turnkey_checks.provider_link_ready.passed'))->toBeTrue()
        ->and(data_get($payload, 'turnkey_checks.provider_link_ready.actual.result.ready'))->toBeTrue();
});

it('runs the pending provider account link blocking scenario', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'turnkey_provider_link_pending_blocks',
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['scenario'])->toBe('turnkey_provider_link_pending_blocks')
        ->and(data_get($payload, 'turnkey_checks.provider_link_pending_blocks.passed'))->toBeTrue()
        ->and(data_get($payload, 'turnkey_checks.provider_link_pending_blocks.actual.latest_status'))->toBe('pending');
});

it('runs fake NetBank and Paynamics provisioning readiness scenarios', function (string $scenario, string $check) {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => $scenario,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['scenario'])->toBe($scenario)
        ->and(data_get($payload, "turnkey_checks.{$check}.passed"))->toBeTrue()
        ->and(data_get($payload, "turnkey_checks.{$check}.actual.ready"))->toBeTrue();
})->with([
    ['turnkey_netbank_bank_account_ready', 'netbank_bank_account_ready'],
    ['turnkey_paynamics_wallet_fake_provisioned', 'paynamics_wallet_fake_provisioned'],
]);

it('runs provider readiness guard scenarios', function (string $scenario, string $check, bool $ready) {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => $scenario,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['scenario'])->toBe($scenario)
        ->and(data_get($payload, "turnkey_checks.{$check}.passed"))->toBeTrue()
        ->and(data_get($payload, "turnkey_checks.{$check}.actual.ready"))->toBe($ready);
})->with([
    ['turnkey_issuer_blocks_missing_provider_wallet', 'issuer_missing_provider_wallet_blocks', false],
    ['turnkey_issuer_allows_ready_provider_wallet', 'issuer_ready_provider_wallet_allows', true],
    ['turnkey_claim_blocks_missing_bank_account', 'claim_missing_bank_account_blocks', false],
    ['turnkey_claim_resumes_after_provider_account_ready', 'claim_ready_provider_account_allows', true],
]);
