<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Providers\XChangeServiceProvider;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

function prepareTreasuryBasicCashIssuer(): FakeLifecycleUser
{
    config()->set(
        'x-change.lifecycle.defaults.user_model',
        FakeLifecycleUser::class,
    );
    config()->set('x-change.lifecycle.treasury_basic_cash.enabled', true);
    config()->set(
        'x-change.lifecycle.treasury_basic_cash.allowed_environments',
        ['testing'],
    );
    enableNetbankTreasuryForTests();
    config()->set('x-change.treasury.legal_entity_reference', null);

    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'Treasury Basic Cash Operator',
        'email' => 'treasury-basic-cash@example.test',
        'password' => bcrypt('password'),
    ]);
    $issuer->setMobileChannel('09171234567');
    $issuer->save();
    $issuer->wallet()->firstOrCreate([
        'slug' => 'platform',
    ], [
        'name' => 'Platform Account',
    ]);

    return $issuer;
}

function runTreasuryBasicCashLifecycle(
    FakeLifecycleUser $issuer,
): mixed {
    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return $key === 'json';
        }
    };

    return app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'treasury_basic_cash',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            json: true,
        ),
    );
}

it('funds Treasury, issues basic_cash, proves replay safety, and rolls back', function () {
    Http::preventStrayRequests();
    $issuer = prepareTreasuryBasicCashIssuer();
    $account = $issuer->wallet()->where('slug', 'platform')->sole();
    $countsBefore = [
        ProviderFundingObservation::query()->count(),
        FundingIntent::query()->count(),
        FundingSettlement::query()->count(),
        TreasuryInventory::query()->count(),
        TreasuryInventoryOperation::query()->count(),
        TreasuryPosition::query()->count(),
        TreasuryPositionOperation::query()->count(),
        DB::table('vouchers')->count(),
    ];

    $result = runTreasuryBasicCashLifecycle($issuer);

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and(data_get($result->payload, 'schema'))
        ->toBe('x-change.lifecycle.treasury-basic-cash.v1')
        ->and(data_get($result->payload, 'success'))->toBeTrue()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'simulation.provider_calls'))->toBe(0)
        ->and(data_get($result->payload, 'simulation.manual_balance_input'))
        ->toBeFalse()
        ->and(config('x-change.treasury.legal_entity_reference'))->toBeNull()
        ->and(data_get($result->payload, 'base_scenario'))->toBe('basic_cash')
        ->and(data_get($result->payload, 'funding.amount_minor'))->toBe(10_000)
        ->and(data_get($result->payload, 'funding.internal_balance_minor'))
        ->toBe(10_000)
        ->and(data_get($result->payload, 'funding.replay_balance_minor'))
        ->toBe(10_000)
        ->and(data_get($result->payload, 'funding.settlement_count'))->toBe(1)
        ->and(data_get($result->payload, 'basic_cash.amount_minor'))->toBe(1_250)
        ->and(data_get($result->payload, 'basic_cash.instruction_fee_minor'))
        ->toBeGreaterThan(0)
        ->and(data_get(
            $result->payload,
            'basic_cash.instruction_fee_position_backed',
        ))->toBeFalse()
        ->and(data_get($result->payload, 'basic_cash.escrow_position_backed'))
        ->toBeFalse()
        ->and(data_get(
            $result->payload,
            'basic_cash.legacy_compatibility_amount_minor',
        ))->toBeGreaterThan(1_250)
        ->and(data_get(
            $result->payload,
            'basic_cash.legacy_balance_after_minor',
        ))->toBe(0)
        ->and(data_get($result->payload, 'basic_cash.issued'))->toBeTrue()
        ->and(data_get($result->payload, 'basic_cash.claimed'))->toBeFalse()
        ->and(data_get(
            $result->payload,
            'balances.after_issuance.wallet_balance_minor',
        ))->toBe(10_000)
        ->and(data_get(
            $result->payload,
            'balances.after_issuance.outstanding_liability_minor',
        ))->toBe(1_250)
        ->and(data_get($result->payload, 'issuance_capacity.before_minor'))
        ->toBe(10_000)
        ->and(data_get($result->payload, 'issuance_capacity.after_minor'))
        ->toBe(8_750)
        ->and(data_get($result->payload, 'steps'))->toHaveCount(8)
        ->and(data_get($result->payload, 'steps.0.key'))
        ->toBe('provider_evidence_verified')
        ->and(data_get($result->payload, 'steps.4.key'))
        ->toBe('pay_code_compatibility_boundary')
        ->and(data_get($result->payload, 'steps.5.key'))
        ->toBe('basic_cash_issued')
        ->and(data_get($result->payload, 'steps.7.key'))
        ->toBe('issuance_capacity_reduced')
        ->and([
            ProviderFundingObservation::query()->count(),
            FundingIntent::query()->count(),
            FundingSettlement::query()->count(),
            TreasuryInventory::query()->count(),
            TreasuryInventoryOperation::query()->count(),
            TreasuryPosition::query()->count(),
            TreasuryPositionOperation::query()->count(),
            DB::table('vouchers')->count(),
        ])->toBe($countsBefore)
        ->and($account->refresh()->getBalanceIntAttribute())->toBe(0);

    $json = json_encode($result->payload, JSON_THROW_ON_ERROR);

    expect($json)
        ->not->toContain('LIFECYCLE-')
        ->not->toContain('provider_transaction_id')
        ->not->toContain('funding_address');
});

it('refuses the Treasury basic_cash lifecycle when its gate is disabled', function () {
    $issuer = prepareTreasuryBasicCashIssuer();
    config()->set('x-change.lifecycle.treasury_basic_cash.enabled', false);

    $result = runTreasuryBasicCashLifecycle($issuer);

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and(data_get($result->payload, 'success'))->toBeFalse()
        ->and(data_get($result->payload, 'steps'))->toBe([])
        ->and(data_get($result->payload, 'message'))->toContain('disabled');
});

it('refuses the Treasury basic_cash lifecycle outside allowed environments', function () {
    $issuer = prepareTreasuryBasicCashIssuer();
    config()->set(
        'x-change.lifecycle.treasury_basic_cash.allowed_environments',
        [],
    );

    $result = runTreasuryBasicCashLifecycle($issuer);

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and(data_get($result->payload, 'success'))->toBeFalse()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'message'))->toContain('disabled');
});

it('reports an actionable failure when the Treasury schema is incomplete', function () {
    $issuer = prepareTreasuryBasicCashIssuer();
    config()->set(
        'x-change.lifecycle.treasury_basic_cash.required_tables',
        ['treasury_positions', 'missing_treasury_table'],
    );

    $result = runTreasuryBasicCashLifecycle($issuer);

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and(data_get($result->payload, 'success'))->toBeFalse()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'missing_tables'))
        ->toBe(['missing_treasury_table'])
        ->and(data_get($result->payload, 'message'))
        ->toContain('php artisan migrate --no-interaction');
});

it('runs the Treasury basic_cash lifecycle through the package command', function () {
    Http::preventStrayRequests();
    $issuer = prepareTreasuryBasicCashIssuer();

    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'treasury_basic_cash',
        '--issuer' => (string) $issuer->getKey(),
        '--json' => true,
    ])
        ->expectsOutputToContain(
            'x-change.lifecycle.treasury-basic-cash.v1',
        )
        ->assertSuccessful();

    expect(ProviderFundingObservation::query()->count())->toBe(0)
        ->and(FundingIntent::query()->count())->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0)
        ->and(TreasuryPosition::query()->count())->toBe(0)
        ->and(TreasuryPositionOperation::query()->count())->toBe(0);
});

it('merges the Treasury basic_cash runner into stale host configuration', function () {
    config()->set('x-change.lifecycle', [
        'scenarios' => [
            'host_defined_scenario' => [
                'label' => 'Host-defined scenario',
                'mode' => 'default',
            ],
        ],
    ]);

    (new XChangeServiceProvider(app()))->register();

    expect(config('x-change.lifecycle.scenarios.host_defined_scenario.label'))
        ->toBe('Host-defined scenario')
        ->and(config('x-change.lifecycle.scenarios.treasury_basic_cash.mode'))
        ->toBe('treasury_basic_cash')
        ->and(config('x-change.lifecycle.treasury_basic_cash.enabled'))
        ->toBeTrue();
});
