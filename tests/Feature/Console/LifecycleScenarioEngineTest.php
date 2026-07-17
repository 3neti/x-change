<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

function prepareEngineLifecycleIssuer(): FakeLifecycleUser
{
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);

    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'Lifecycle Issuer',
        'email' => 'issuer@example.test',
        'password' => bcrypt('password'),
    ]);

    $issuer->setMobileChannel('09171234567');
    $issuer->save();

    fundTestUserWallet($issuer);

    return $issuer;
}

it('returns failure result for unknown lifecycle scenario', function () {
    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return false;
        }
    };

    $result = app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'missing_scenario_key',
        options: new LifecycleScenarioRunOptions(json: true),
    );

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and($result->payload['success'])->toBeFalse()
        ->and($result->payload['message'])->toContain('Unknown scenario');
});

it('returns failure result for missing selected attempt', function () {
    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return false;
        }
    };

    $result = app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'secret_required',
        options: new LifecycleScenarioRunOptions(
            onlyAttempt: 'missing',
            json: true,
        ),
    );

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and($result->payload['success'])->toBeFalse()
        ->and($result->payload['message'])->toContain('Unknown attempt');
});

it('runs no-claim lifecycle scenario through the engine', function () {
    $issuer = prepareEngineLifecycleIssuer();

    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return false;
        }

        public function info($string, $verbosity = null): void {}

        public function line($string, $style = null, $verbosity = null): void {}
    };

    $result = app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'basic_cash',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            wallet: (string) $issuer->getKey(),
            noClaim: true,
            json: true,
        ),
    );

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and($result->payload['scenario'])->toBe('basic_cash')
        ->and($result->payload['generated'])->toBeArray()
        ->and(data_get($result->payload, 'attempt_summary.total'))->not->toBeNull();
});

it('demonstrates voucher liability money semantics through a no-claim lifecycle scenario', function () {
    $issuer = prepareEngineLifecycleIssuer();

    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return false;
        }

        public function info($string, $verbosity = null): void {}

        public function line($string, $style = null, $verbosity = null): void {}
    };

    $result = app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'money_semantics_voucher_liability_demo',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            wallet: (string) $issuer->getKey(),
            noClaim: true,
            json: true,
        ),
    );

    $before = data_get($result->payload, 'money_semantics.before_issuance');
    $after = data_get($result->payload, 'money_semantics.after_issuance');

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and($result->payload['scenario'])->toBe('money_semantics_voucher_liability_demo')
        ->and(data_get($result->payload, 'money_semantics.behavior'))->toBe('debit_at_issuance')
        ->and(data_get($result->payload, 'money_movement_decision.status'))->toBe('decision_required')
        ->and(data_get($result->payload, 'money_movement_decision.current_model'))->toBe('debit_at_issuance')
        ->and(data_get($result->payload, 'money_movement_decision.redactions.mutates_wallets'))->toBeFalse()
        ->and(data_get($result->payload, 'money_movement_decision.redactions.reserves_funds'))->toBeFalse()
        ->and(data_get($result->payload, 'money_movement_decision.redactions.releases_funds'))->toBeFalse()
        ->and($before)->toBeArray()
        ->and($after)->toBeArray()
        ->and(data_get($after, 'wallet_balance_minor'))->toBeLessThan(data_get($before, 'wallet_balance_minor'))
        ->and(data_get($after, 'outstanding_liability_minor'))->toBeGreaterThan(data_get($before, 'outstanding_liability_minor'))
        ->and(data_get($after, 'read_only'))->toBeTrue()
        ->and(data_get($after, 'redactions.mutates_wallets'))->toBeFalse()
        ->and(data_get($after, 'redactions.releases_funds'))->toBeFalse();
});

it('runs sequential claim scenarios through the registered runner', function () {
    $issuer = prepareEngineLifecycleIssuer();

    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return match ($key) {
                'json' => true,
                'accept-pending' => true,
                default => false,
            };
        }

        public function info($string, $verbosity = null): void {}

        public function line($string, $style = null, $verbosity = null): void {}

        public function error($string, $verbosity = null): void {}
    };

    $result = app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'collectible_basic_payment',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            wallet: (string) $issuer->getKey(),
            json: true,
            acceptPending: true,
        ),
    );

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and($result->payload['_bridge'] ?? null)->toBeNull()
        ->and($result->payload['scenario'])->toBe('collectible_basic_payment')
        ->and($result->payload['claims'])->toBeArray()
        ->and($result->payload['attempt_summary'])->toBeArray();
});
