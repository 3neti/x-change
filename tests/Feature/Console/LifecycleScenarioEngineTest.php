<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioEngine;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioRunOptions;
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
    $command = new class extends Command {
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
    $command = new class extends Command {
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

    $command = new class extends Command {
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

it('bridges sequential claim scenarios for now', function () {
    $issuer = prepareEngineLifecycleIssuer();

    $command = new class extends Command {
        public function option($key = null): mixed
        {
            return false;
        }

        public function info($string, $verbosity = null): void {}

        public function line($string, $style = null, $verbosity = null): void {}
    };

    $result = app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'collectible_basic_payment',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            wallet: (string) $issuer->getKey(),
            json: true,
        ),
    );

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and($result->payload['_bridge'])->toBe('sequential_claims')
        ->and($result->payload['bootstrap'])->not->toBeNull();
});
