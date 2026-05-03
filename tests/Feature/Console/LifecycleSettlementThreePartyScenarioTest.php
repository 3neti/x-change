<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.lifecycle.defaults.system_user_email' => 'system@example.test',
        'x-change.lifecycle.defaults.test_user_email' => 'lester@hurtado.ph',
        'x-change.lifecycle.defaults.test_user_mobile' => '09173011987',
        'x-change.withdrawal.open_slice_min_interval_seconds' => 0,
        'x-change.settlement.default_driver' => 'philhealth-bst',
        'x-change.settlement.drivers_path' => settlementEnvelopeDriversPath(),
        'queue.default' => 'sync',
    ]);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('runs the philhealth bst three party settlement lifecycle scenario', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'settlement_philhealth_bst_three_party',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($json['scenario'])->toBe('settlement_philhealth_bst_three_party')
        ->and($json['mode'])->toBe('settlement_three_party_flow')
        ->and($json['roles'])->toMatchArray([
            'issuer' => 'hospital',
            'attestor' => 'patient',
            'payer' => 'philhealth',
            'recipient' => 'hospital',
        ])
        ->and($json['phase_summary'])->toMatchArray([
            'passed' => 5,
            'failed' => 0,
            'total' => 5,
        ]);
});

it('shows patient attestation separate from settlement payment', function () {
    Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'settlement_philhealth_bst_three_party',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($json['phases']['issue']['role'])->toBe('hospital')
        ->and($json['phases']['attest']['role'])->toBe('patient')
        ->and($json['phases']['attest']['claim_type'])->toBe('redeem')
        ->and($json['phases']['attest']['disbursement'])->toBeFalse()
        ->and($json['phases']['settle']['role'])->toBe('philhealth')
        ->and($json['phases']['settle']['recipient_role'])->toBe('hospital');
});

it('shows envelope blocked before verification and settleable after completion', function () {
    Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'settlement_philhealth_bst_three_party',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($json['phases']['evaluate_before_completion']['status'])->toBe('blocked')
        ->and($json['phases']['evaluate_before_completion']['settlement']['ready'])->toBeFalse()
        ->and($json['phases']['evaluate_before_completion']['settlement']['missing'])
        ->toContain('amount_verified');

    expect($json['phases']['complete_envelope']['status'])->toBe('ready')
        ->and($json['phases']['complete_envelope']['settlement']['ready'])->toBeTrue()
        ->and($json['phases']['complete_envelope']['settlement']['satisfied'])
        ->toContain('payload_present')
        ->and($json['phases']['complete_envelope']['settlement']['satisfied'])
        ->toContain('amount_verified');
});
