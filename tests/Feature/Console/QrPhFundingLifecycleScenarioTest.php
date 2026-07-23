<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

function prepareQrPhFundingLifecycleIssuer(): FakeLifecycleUser
{
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);
    config()->set('x-change.lifecycle.qrph_funding_simulation.enabled', true);
    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'QR Ph Scenario User',
        'email' => 'qrph-scenario@example.test',
        'password' => bcrypt('password'),
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ]);
    fundTestUserWallet($issuer);

    return $issuer;
}

function runQrPhFundingLifecycle(FakeLifecycleUser $issuer): mixed
{
    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return $key === 'json';
        }
    };

    return app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'qrph_funding_existing_mobile_demo',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            json: true,
        ),
    );
}

it('runs the signed QR Ph funding pipeline exactly once and rolls every change back', function () {
    Http::preventStrayRequests();
    $issuer = prepareQrPhFundingLifecycleIssuer();
    $balanceBefore = (int) $issuer->wallet->balance;
    $countsBefore = [
        FundingIntent::query()->count(),
        SimulatedFundingTransaction::query()->count(),
        FundingSettlement::query()->count(),
        DB::table('webhook_receipts')->count(),
        DB::table('provider_funding_observations')->count(),
    ];

    $result = runQrPhFundingLifecycle($issuer);

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and(data_get($result->payload, 'schema'))->toBe('x-change.lifecycle.qrph-funding-simulation.v1')
        ->and(data_get($result->payload, 'success'))->toBeTrue()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'simulation.signed_webhook'))->toBeTrue()
        ->and(data_get($result->payload, 'simulation.authoritative_verification'))->toBeTrue()
        ->and(data_get($result->payload, 'balance.credited_minor'))->toBe(2_500)
        ->and(data_get($result->payload, 'balance.after_replay_minor'))
        ->toBe(data_get($result->payload, 'balance.after_minor'))
        ->and(data_get($result->payload, 'steps'))->toHaveCount(7)
        ->and(data_get($result->payload, 'steps.0.key'))->toBe('verified_mobile_resolved')
        ->and(data_get($result->payload, 'steps.4.key'))->toBe('provider_evidence_verified')
        ->and(data_get($result->payload, 'steps.6.key'))->toBe('identical_replay_noop')
        ->and([
            FundingIntent::query()->count(),
            SimulatedFundingTransaction::query()->count(),
            FundingSettlement::query()->count(),
            DB::table('webhook_receipts')->count(),
            DB::table('provider_funding_observations')->count(),
        ])->toBe($countsBefore)
        ->and((int) $issuer->wallet->refresh()->balance)->toBe($balanceBefore);

    $json = json_encode($result->payload, JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('639173011987')
        ->not->toContain('qrph-lifecycle-simulator-signing-key')
        ->not->toContain('qrph-lifecycle-simulator-mobile-key')
        ->not->toContain('qrph-lifecycle-payer-identity-key');
});

it('refuses the QR Ph funding lifecycle when its scenario gate is disabled', function () {
    $issuer = prepareQrPhFundingLifecycleIssuer();
    config()->set('x-change.lifecycle.qrph_funding_simulation.enabled', false);

    $result = runQrPhFundingLifecycle($issuer);

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and(data_get($result->payload, 'success'))->toBeFalse()
        ->and(data_get($result->payload, 'message'))->toContain('disabled');
});

it('runs the QR Ph funding lifecycle through the package command', function () {
    $issuer = prepareQrPhFundingLifecycleIssuer();

    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'qrph_funding_existing_mobile_demo',
        '--issuer' => (string) $issuer->getKey(),
        '--json' => true,
    ])->assertSuccessful();

    expect(FundingIntent::query()->count())->toBe(0)
        ->and(SimulatedFundingTransaction::query()->count())->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0);
});
