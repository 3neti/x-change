<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

function prepareAccountManagementLifecycleIssuer(): FakeLifecycleUser
{
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);
    config()->set('x-change.cockpit.account_scenario.enabled', true);

    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'Account Scenario Operator',
        'email' => 'account-scenario@example.test',
        'password' => bcrypt('password'),
    ]);
    $issuer->setMobileChannel('09171234567');
    $issuer->save();
    fundTestUserWallet($issuer);

    return $issuer;
}

function runAccountManagementLifecycle(FakeLifecycleUser $issuer): mixed
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
        scenarioKey: 'account_management_funding_destinations_demo',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            json: true,
        ),
    );
}

it('runs the rollback-only account-management lifecycle without provider calls or durable state', function () {
    Http::preventStrayRequests();
    $issuer = prepareAccountManagementLifecycleIssuer();
    $balanceBefore = (int) $issuer->wallet->balance;
    $countsBefore = [
        FundingDestinationPreference::query()->count(),
        ProviderAccountLink::query()->count(),
        FundingIntent::query()->count(),
    ];

    $result = runAccountManagementLifecycle($issuer);

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and(data_get($result->payload, 'schema'))->toBe('x-change.lifecycle.account-management-scenario.v1')
        ->and(data_get($result->payload, 'success'))->toBeTrue()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'simulation.provider_calls'))->toBe(0)
        ->and(data_get($result->payload, 'simulation.balance_changed'))->toBeFalse()
        ->and(data_get($result->payload, 'simulation.persisted'))->toBeFalse()
        ->and(data_get($result->payload, 'steps'))->toHaveCount(7)
        ->and(data_get($result->payload, 'steps.0.key'))->toBe('shared_defaults')
        ->and(data_get($result->payload, 'steps.3.key'))->toBe('netbank_token_rotation')
        ->and(data_get($result->payload, 'steps.4.outcome'))->toBe('blocked')
        ->and(data_get($result->payload, 'steps.6.key'))->toBe('shared_restored_history_retained')
        ->and([
            FundingDestinationPreference::query()->count(),
            ProviderAccountLink::query()->count(),
            FundingIntent::query()->count(),
        ])->toBe($countsBefore)
        ->and((int) $issuer->wallet->refresh()->balance)->toBe($balanceBefore);

    $json = json_encode($result->payload, JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('991100004242')
        ->not->toContain('SCENARIO-DEMO-WALLET-654321')
        ->not->toContain('scenario-write-only-netbank-token')
        ->not->toContain('scenario-rotated-write-only-token')
        ->not->toContain('scenario-shared-token');
});

it('restores existing owner state after the account-management lifecycle', function () {
    $issuer = prepareAccountManagementLifecycleIssuer();
    $preference = FundingDestinationPreference::query()->create([
        'owner_type' => $issuer::class,
        'owner_id' => $issuer->getKey(),
        'provider_code' => 'netbank',
        'mode' => 'shared',
        'version' => 7,
        'changed_by_type' => $issuer::class,
        'changed_by_id' => (string) $issuer->getKey(),
    ]);
    $rawBefore = DB::table('x_change_funding_destination_preferences')
        ->find($preference->getKey());

    $result = runAccountManagementLifecycle($issuer);
    $rawAfter = DB::table('x_change_funding_destination_preferences')
        ->find($preference->getKey());

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and((array) $rawAfter)->toBe((array) $rawBefore);
});

it('refuses the account-management lifecycle when its environment gate is disabled', function () {
    $issuer = prepareAccountManagementLifecycleIssuer();
    config()->set('x-change.cockpit.account_scenario.enabled', false);

    $result = runAccountManagementLifecycle($issuer);

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and(data_get($result->payload, 'success'))->toBeFalse()
        ->and(data_get($result->payload, 'steps'))->toBe([])
        ->and(data_get($result->payload, 'message'))->toContain('disabled');
});

it('runs the account-management scenario through the lifecycle command', function () {
    $issuer = prepareAccountManagementLifecycleIssuer();

    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'account_management_funding_destinations_demo',
        '--issuer' => (string) $issuer->getKey(),
        '--json' => true,
    ])->assertSuccessful();

    expect(FundingDestinationPreference::query()->count())->toBe(0)
        ->and(ProviderAccountLink::query()->count())->toBe(0)
        ->and(FundingIntent::query()->count())->toBe(0);
});
