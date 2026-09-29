<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

function prepareOnDemandFundingLifecycleIssuer(): FakeLifecycleUser
{
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);
    config()->set('x-change.lifecycle.qrph_funding_simulation.enabled', true);
    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'On-Demand Scenario User',
        'email' => 'on-demand-scenario@example.test',
        'password' => bcrypt('password'),
    ]);
    $issuer->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ])->save();
    fundTestUserWallet($issuer);

    return $issuer;
}

it('runs the package-owned fixed QR Ph issuance-funding safety lifecycle and rolls back', function (): void {
    Http::preventStrayRequests();
    $issuer = prepareOnDemandFundingLifecycleIssuer();
    $balanceBefore = (int) $issuer->wallet->balance;
    $countsBefore = [
        FundingIntent::query()->count(),
        PayCodeIssuanceFundingOrder::query()->count(),
        SimulatedFundingTransaction::query()->count(),
        FundingSettlement::query()->count(),
        DB::table('webhook_receipts')->count(),
        DB::table('provider_funding_observations')->count(),
    ];
    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return $key === 'json';
        }
    };

    $result = app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'on_demand_issuance_fixed_qr_demo',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            json: true,
        ),
    );

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and(data_get($result->payload, 'schema'))->toBe('x-change.lifecycle.on-demand-fixed-qr-ph.v1')
        ->and(data_get($result->payload, 'success'))->toBeTrue()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'simulation.provider_calls'))->toBe(0)
        ->and(data_get($result->payload, 'artifacts.qr_present'))->toBeTrue()
        ->and(data_get($result->payload, 'steps'))->toHaveCount(5)
        ->and(data_get($result->payload, 'steps.1.key'))->toBe('fixed_qr_issued')
        ->and(data_get($result->payload, 'steps.2.key'))->toBe('collision_safe_lease')
        ->and(data_get($result->payload, 'steps.4.key'))->toBe('late_payment_disposed')
        ->and([
            FundingIntent::query()->count(),
            PayCodeIssuanceFundingOrder::query()->count(),
            SimulatedFundingTransaction::query()->count(),
            FundingSettlement::query()->count(),
            DB::table('webhook_receipts')->count(),
            DB::table('provider_funding_observations')->count(),
        ])->toBe($countsBefore)
        ->and((int) $issuer->wallet->refresh()->balance)->toBe($balanceBefore);

    expect(json_encode($result->payload, JSON_THROW_ON_ERROR))
        ->not->toContain('639173011987')
        ->not->toContain('on-demand-lifecycle-signing-key')
        ->not->toContain('on-demand-lifecycle-mobile-key');
});

it('runs the rollback-only lifecycle in a production-like staging environment', function (): void {
    Http::preventStrayRequests();
    $this->app->detectEnvironment(fn (): string => 'staging');
    $issuer = prepareOnDemandFundingLifecycleIssuer();
    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return $key === 'json';
        }
    };

    $result = app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'on_demand_issuance_fixed_qr_demo',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            json: true,
        ),
    );

    expect($this->app->environment('staging'))->toBeTrue()
        ->and($this->app->isProduction())->toBeFalse()
        ->and($result->exitCode)->toBe(Command::SUCCESS)
        ->and(data_get($result->payload, 'success'))->toBeTrue()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'simulation.provider_calls'))->toBe(0)
        ->and(PayCodeIssuanceFundingOrder::query()->count())->toBe(0);
});

it('runs the fixed QR Ph issuance-funding lifecycle through the package command', function (): void {
    $issuer = prepareOnDemandFundingLifecycleIssuer();

    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'on_demand_issuance_fixed_qr_demo',
        '--issuer' => (string) $issuer->getKey(),
        '--json' => true,
    ])->assertSuccessful();

    expect(FundingIntent::query()->count())->toBe(0)
        ->and(PayCodeIssuanceFundingOrder::query()->count())->toBe(0)
        ->and(SimulatedFundingTransaction::query()->count())->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0);
});
