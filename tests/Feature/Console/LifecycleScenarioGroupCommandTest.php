<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

it('runs smoke group', function () {
    config()->set('x-change.lifecycle.scenarios', []);
    config()->set('x-change.lifecycle.scenario_groups', [
        'smoke-test' => [
            'categories' => ['smoke'],
        ],
    ]);

    $this->artisan('xchange:lifecycle:run-group', [
        'group' => 'smoke-test',
    ])->assertExitCode(Command::SUCCESS);
});

it('renders json', function () {
    config()->set('x-change.lifecycle.scenarios', []);
    config()->set('x-change.lifecycle.scenario_groups', [
        'smoke-test' => [
            'categories' => ['smoke'],
        ],
    ]);

    $exitCode = Artisan::call('xchange:lifecycle:run-group', [
        'group' => 'smoke-test',
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($payload)->toBeArray()
        ->and($payload['group'])->toBe('smoke-test')
        ->and($payload['successful'])->toBeTrue()
        ->and($payload['summary']['total'])->toBe(0)
        ->and($payload['summary']['passed'])->toBe(0)
        ->and($payload['summary']['failed'])->toBe(0);
});

it('returns failure for unknown group', function () {
    config()->set('x-change.lifecycle.scenario_groups', []);

    $exitCode = Artisan::call('xchange:lifecycle:run-group', [
        'group' => 'unknown-group',
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($payload)->toBeArray()
        ->and($payload['group'])->toBe('unknown-group')
        ->and($payload['successful'])->toBeFalse()
        ->and($payload['message'])->toContain('Unknown lifecycle scenario group');
});

it('stop-on-failure stops early', function () {
    config()->set('x-change.lifecycle.scenarios.failing_group_scenario', [
        'label' => 'Failing Group Scenario',
        'category' => 'smoke',
        'mode' => 'unsupported_group_test_mode',
    ]);

    config()->set('x-change.lifecycle.scenarios.second_group_scenario', [
        'label' => 'Second Group Scenario',
        'category' => 'smoke',
        'mode' => 'unsupported_second_mode',
    ]);

    config()->set('x-change.lifecycle.scenario_groups', [
        'stop-test' => [
            'scenarios' => [
                'failing_group_scenario',
                'second_group_scenario',
            ],
        ],
    ]);

    $exitCode = Artisan::call('xchange:lifecycle:run-group', [
        'group' => 'stop-test',
        '--stop-on-failure' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($payload)->toBeArray()
        ->and($payload['group'])->toBe('stop-test')
        ->and($payload['successful'])->toBeFalse()
        ->and($payload['summary']['total'])->toBe(1)
        ->and($payload['summary']['passed'])->toBe(0)
        ->and($payload['summary']['failed'])->toBe(1)
        ->and($payload['results'])->toHaveKey('failing_group_scenario')
        ->and($payload['results'])->not->toHaveKey('second_group_scenario')
        ->and($payload['results']['failing_group_scenario']['exit_code'])->toBe(Command::FAILURE);
});
