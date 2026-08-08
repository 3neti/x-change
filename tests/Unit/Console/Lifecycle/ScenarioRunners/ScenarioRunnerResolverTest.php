<?php

declare(strict_types=1);

use LBHurtado\XChange\Lifecycle\Runners\AccountManagementScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\CommercialOperationsSimulationScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\DefaultClaimScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\ExecutionEngineContractScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunnerResolver;
use LBHurtado\XChange\Lifecycle\Runners\SequentialClaimsScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\SettlementEnvelopeEvaluationScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\SettlementThreePartyScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\TreasuryBasicCashScenarioRunner;

it('resolves default scenarios to default claim runner', function () {
    $resolution = app(ScenarioRunnerResolver::class)->resolve([
        'mode' => 'default',
    ]);

    expect($resolution->mode)->toBe('default')
        ->and($resolution->runner)->toBeInstanceOf(DefaultClaimScenarioRunner::class);
});

it('infers sequential claims mode when scenario has claims', function () {
    $resolution = app(ScenarioRunnerResolver::class)->resolve([
        'claims' => [
            'first' => [],
        ],
    ]);

    expect($resolution->mode)->toBe('sequential_claims')
        ->and($resolution->scenario['mode'])->toBe('sequential_claims')
        ->and($resolution->runner)->toBeInstanceOf(SequentialClaimsScenarioRunner::class);
});

it('does not override explicit scenario mode even when claims exist', function () {
    $resolution = app(ScenarioRunnerResolver::class)->resolve([
        'mode' => 'settlement_envelope_evaluation',
        'claims' => [
            'first' => [],
        ],
    ]);

    expect($resolution->mode)->toBe('settlement_envelope_evaluation')
        ->and($resolution->runner)->toBeInstanceOf(SettlementEnvelopeEvaluationScenarioRunner::class);
});

it('resolves settlement three party mode', function () {
    $resolution = app(ScenarioRunnerResolver::class)->resolve([
        'mode' => 'settlement_three_party_flow',
    ]);

    expect($resolution->mode)->toBe('settlement_three_party_flow')
        ->and($resolution->runner)->toBeInstanceOf(SettlementThreePartyScenarioRunner::class);
});

it('resolves execution engine contract demo mode', function () {
    $resolution = app(ScenarioRunnerResolver::class)->resolve([
        'mode' => 'execution_engine_contract_demo',
    ]);

    expect($resolution->mode)->toBe('execution_engine_contract_demo')
        ->and($resolution->runner)->toBeInstanceOf(ExecutionEngineContractScenarioRunner::class);
});

it('resolves account management mode', function () {
    $resolution = app(ScenarioRunnerResolver::class)->resolve([
        'mode' => 'account_management',
    ]);

    expect($resolution->mode)->toBe('account_management')
        ->and($resolution->runner)->toBeInstanceOf(AccountManagementScenarioRunner::class);
});

it('resolves Treasury basic cash mode', function () {
    $resolution = app(ScenarioRunnerResolver::class)->resolve([
        'mode' => 'treasury_basic_cash',
    ]);

    expect($resolution->mode)->toBe('treasury_basic_cash')
        ->and($resolution->runner)
        ->toBeInstanceOf(TreasuryBasicCashScenarioRunner::class);
});

it('resolves commercial operations simulation mode', function () {
    $resolution = app(ScenarioRunnerResolver::class)->resolve([
        'mode' => 'commercial_operations_simulation',
    ]);

    expect($resolution->mode)->toBe('commercial_operations_simulation')
        ->and($resolution->runner)
        ->toBeInstanceOf(CommercialOperationsSimulationScenarioRunner::class);
});

it('throws when no runner is registered for mode', function () {
    app(ScenarioRunnerResolver::class)->resolve([
        'mode' => 'missing_mode',
    ]);
})->throws(RuntimeException::class, 'No lifecycle scenario runner registered');
