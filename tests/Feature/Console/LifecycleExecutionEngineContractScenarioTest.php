<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.lifecycle.defaults.system_user_email' => 'system@example.test',
        'x-change.lifecycle.defaults.test_user_email' => 'lester@hurtado.ph',
        'x-change.lifecycle.defaults.test_user_mobile' => '09173011987',
        'x-change.settlement.default_driver' => 'philhealth-bst',
        'x-change.settlement.drivers_path' => settlementEnvelopeDriversPath(),
        'queue.default' => 'sync',
    ]);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('runs settlement envelope execution through the voucher engine and x-change gateway binding', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($json['scenario'])->toBe('execution_settlement_envelope_contract_demo')
        ->and($json['mode'])->toBe('execution_engine_contract_demo')
        ->and(data_get($json, 'execution.driver'))->toBe('settlement_envelope')
        ->and(data_get($json, 'execution.status'))->toBe('succeeded')
        ->and(data_get($json, 'execution.execution_id'))->toBeString()
        ->and(data_get($json, 'execution.events'))->toContain('settlement_envelope.loaded')
        ->and(data_get($json, 'execution.events'))->toContain('settlement_envelope.ready')
        ->and(data_get($json, 'execution.events'))->toContain('settlement_envelope.locked')
        ->and(data_get($json, 'execution.metadata.envelope_reference'))->toBe('ENV-LIFECYCLE-001')
        ->and(data_get($json, 'execution_instruction.metadata.settlement_envelope.reference'))->toBe('ENV-LIFECYCLE-001')
        ->and(data_get($json, 'contract_boundary.gateway_owner'))->toBe('x-change');
});

it('runs stored value activation and spend through the voucher engine and x-change gateway binding', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_stored_value_contract_demo',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($json['scenario'])->toBe('execution_stored_value_contract_demo')
        ->and($json['mode'])->toBe('execution_engine_contract_demo')
        ->and($json['executions'])->toHaveCount(2)
        ->and(data_get($json, 'executions.0.operation'))->toBe('activate')
        ->and(data_get($json, 'executions.0.driver'))->toBe('stored_value')
        ->and(data_get($json, 'executions.0.events'))->toContain('stored_value.activated')
        ->and(data_get($json, 'executions.0.metadata.remaining_balance'))->toBe(10000)
        ->and(data_get($json, 'executions.1.operation'))->toBe('spend')
        ->and(data_get($json, 'executions.1.driver'))->toBe('stored_value')
        ->and(data_get($json, 'executions.1.events'))->toContain('stored_value.spent')
        ->and(data_get($json, 'executions.1.metadata.spent_amount'))->toBe(2500)
        ->and(data_get($json, 'executions.1.metadata.remaining_balance'))->toBe(7500)
        ->and(data_get($json, 'execution_instruction.metadata.stored_value.reference'))->toBe('SV-LIFECYCLE-001')
        ->and(data_get($json, 'contract_boundary.wallet_side_effects'))->toBe('not-performed');
});

it('requires explicit live-provider approval for the execution engine live cash transfer scenario', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_engine_basic_cash_live_transfer',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($json['scenario'])->toBe('execution_engine_basic_cash_live_transfer')
        ->and($json['success'])->toBeFalse()
        ->and($json['message'])->toContain('--live-provider');
});

it('runs a fake-provider live cash transfer through the voucher execution engine', function () {
    config([
        'x-change.provider_runtime.lifecycle.allow_live_provider_scenarios' => true,
    ]);

    $provider = fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'TXN-ENGINE-LIVE',
        uuid: 'uuid-engine-live',
        provider: 'netbank',
    );

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_engine_basic_cash_live_transfer',
        '--live-provider' => true,
        '--json' => true,
        '--poll' => 1,
        '--max-polls' => 1,
    ]);

    $voucher = Voucher::query()->latest('id')->firstOrFail();
    $reconciliation = DisbursementReconciliation::query()
        ->where('voucher_code', $voucher->code)
        ->latest('id')
        ->firstOrFail();

    expect($exitCode)->toBe(0)
        ->and(data_get($voucher->instructions?->toArray(), 'execution.driver'))->toBe('x_change_live_cash')
        ->and($voucher->isRedeemed())->toBeTrue()
        ->and($reconciliation->provider)->toBe('netbank')
        ->and($reconciliation->provider_transaction_id)->toBe('TXN-ENGINE-LIVE')
        ->and($reconciliation->status)->toBe('succeeded')
        ->and($reconciliation->settlement_rail)->toBe('INSTAPAY')
        ->and($reconciliation->account_number_masked)->toBe('*******1987');

    $provider->assertDisburseCalledTimes(1);
});
