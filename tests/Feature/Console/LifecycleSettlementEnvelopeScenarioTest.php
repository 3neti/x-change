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

    expect(FakeLifecycleUser::class)->not->toBe('');
    expect(class_exists(FakeLifecycleUser::class))->toBeTrue();
    expect(config('x-change.lifecycle.defaults.user_model'))->toBe(FakeLifecycleUser::class);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('runs the philhealth bst settlement envelope lifecycle scenario', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'settlement_philhealth_bst',
        '--json' => true,
    ]);

    $output = Artisan::output();
    $json = json_decode($output, true);

    expect($exitCode)->toBe(0)
        ->and($json['scenario'])->toBe('settlement_philhealth_bst')
        ->and($json['mode'])->toBe('settlement_envelope_evaluation')
        ->and($json['attempt_summary'])->toMatchArray([
            'passed' => 2,
            'failed' => 0,
            'total' => 2,
        ]);
});

it('shows blocked then ready settlement envelope states', function () {
    Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'settlement_philhealth_bst',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    $blocked = $json['attempts'][0];
    $ready = $json['attempts'][1];

    expect($blocked['status'])->toBe('blocked')
        ->and($blocked['settlement']['ready'])->toBeFalse()
        ->and($blocked['settlement']['satisfied'])->toContain('payload_present')
        ->and($blocked['settlement']['missing'])->toContain('amount_verified')
        ->and($blocked['evaluation']['passed'])->toBeTrue();

    expect($ready['status'])->toBe('ready')
        ->and($ready['settlement']['ready'])->toBeTrue()
        ->and($ready['settlement']['satisfied'])->toContain('payload_present')
        ->and($ready['settlement']['satisfied'])->toContain('amount_verified')
        ->and($ready['settlement']['missing'])->toBeEmpty()
        ->and($ready['evaluation']['passed'])->toBeTrue();
});

it('does not submit a claim or disburse during settlement envelope evaluation mode', function () {
    Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'settlement_philhealth_bst',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($json)->not->toHaveKey('reconciliation')
        ->and($json['attempts'][0])->not->toHaveKey('claim')
        ->and($json['attempts'][0])->not->toHaveKey('disbursement_check')
        ->and($json['attempts'][1])->not->toHaveKey('claim')
        ->and($json['attempts'][1])->not->toHaveKey('disbursement_check');
});

it('uses philhealth bst as the canonical driver in lifecycle settlement evaluation', function () {
    Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'settlement_philhealth_bst',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    foreach ($json['attempts'] as $attempt) {
        expect($attempt['settlement']['driver'])->toBe('philhealth-bst')
            ->and($attempt['settlement']['gate'])->toBe('settleable')
            ->and($attempt['settlement']['meta']['driver_path'])->toContain('philhealth-bst.yaml');
    }
});
