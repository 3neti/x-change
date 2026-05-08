<?php

declare(strict_types=1);

use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioGroupRepository;

it('normalizes configured groups', function () {
    config()->set('x-change.lifecycle.scenarios', []);
    config()->set('x-change.lifecycle.scenario_groups', [
        'pre-deployment' => [
            'label' => 'Pre-Deployment Checks',
            'description' => 'Runs critical checks before deployment.',
            'categories' => ['smoke', 'contract'],
            'tags' => ['wallet'],
            'scenarios' => ['basic_cash'],
        ],
    ]);

    $group = app(LifecycleScenarioGroupRepository::class)->findOrFail('pre-deployment');

    expect($group['key'])->toBe('pre-deployment')
        ->and($group['label'])->toBe('Pre-Deployment Checks')
        ->and($group['description'])->toBe('Runs critical checks before deployment.')
        ->and($group['categories'])->toBe(['smoke', 'contract'])
        ->and($group['tags'])->toBe(['wallet'])
        ->and($group['scenarios'])->toBe(['basic_cash']);
});

it('treats known categories as implicit groups', function () {
    config()->set('x-change.lifecycle.scenario_groups', []);

    $group = app(LifecycleScenarioGroupRepository::class)->findOrFail('smoke');

    expect($group['key'])->toBe('smoke')
        ->and($group['label'])->toBe('Smoke')
        ->and($group['categories'])->toBe(['smoke'])
        ->and($group['tags'])->toBe([])
        ->and($group['scenarios'])->toBe([]);
});

it('returns scenarios by category', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'category' => 'smoke',
        ],
        'secret_required' => [
            'category' => 'contract',
        ],
    ]);

    config()->set('x-change.lifecycle.scenario_groups', [
        'smoke-only' => [
            'categories' => ['smoke'],
        ],
    ]);

    $scenarios = app(LifecycleScenarioGroupRepository::class)->scenariosFor('smoke-only');

    expect(array_keys($scenarios))->toBe(['basic_cash']);
});

it('returns scenarios by tag', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'tags' => ['voucher', 'demo'],
        ],
        'wallet_debit_smoke' => [
            'tags' => ['wallet'],
        ],
    ]);

    config()->set('x-change.lifecycle.scenario_groups', [
        'demo' => [
            'tags' => ['demo'],
        ],
    ]);

    $scenarios = app(LifecycleScenarioGroupRepository::class)->scenariosFor('demo');

    expect(array_keys($scenarios))->toBe(['basic_cash']);
});

it('returns explicit scenarios', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'category' => 'smoke',
        ],
        'secret_required' => [
            'category' => 'contract',
        ],
    ]);

    config()->set('x-change.lifecycle.scenario_groups', [
        'selected' => [
            'scenarios' => ['secret_required'],
        ],
    ]);

    $scenarios = app(LifecycleScenarioGroupRepository::class)->scenariosFor('selected');

    expect(array_keys($scenarios))->toBe(['secret_required']);
});

it('throws when group references unknown scenario', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'category' => 'smoke',
        ],
    ]);

    config()->set('x-change.lifecycle.scenario_groups', [
        'broken' => [
            'scenarios' => ['missing_scenario'],
        ],
    ]);

    app(LifecycleScenarioGroupRepository::class)->scenariosFor('broken');
})->throws(InvalidArgumentException::class, 'references unknown scenario');

it('throws for unknown non-category group', function () {
    config()->set('x-change.lifecycle.scenario_groups', []);

    app(LifecycleScenarioGroupRepository::class)->findOrFail('unknown-group');
})->throws(InvalidArgumentException::class, 'Unknown lifecycle scenario group');
