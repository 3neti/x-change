<?php

declare(strict_types=1);

use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;

it('loads lifecycle scenarios from config', function () {
    $repository = app(LifecycleScenarioRepository::class);

    expect($repository->all())->toBeArray()
        ->and($repository->keys())->toBeArray()
        ->and($repository->keys())->not->toBeEmpty();
});

it('finds a lifecycle scenario by key', function () {
    $repository = app(LifecycleScenarioRepository::class);

    $key = $repository->keys()[0];

    expect($repository->find($key))->toBeArray()
        ->and($repository->findOrFail($key))->toBeArray();
});

it('returns null when lifecycle scenario key does not exist', function () {
    expect(app(LifecycleScenarioRepository::class)->find('missing_scenario_key'))->toBeNull();
});

it('throws when lifecycle scenario key does not exist', function () {
    app(LifecycleScenarioRepository::class)
        ->findOrFail('missing_scenario_key');
})->throws(InvalidArgumentException::class, 'Unknown scenario');

it('normalizes scenario metadata defaults', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'label' => 'Basic Cash',
        ],
    ]);

    $repository = app(LifecycleScenarioRepository::class);

    $scenario = $repository->findOrFail('basic_cash');

    expect($scenario['key'])->toBe('basic_cash')
        ->and($scenario['label'])->toBe('Basic Cash')
        ->and($scenario['category'])->toBe('smoke')
        ->and($scenario)->not->toHaveKey('mode')
        ->and($repository->modeFor($scenario))->toBe('default')
        ->and($scenario['tags'])->toBe([])
        ->and($scenario['risk'])->toBe('medium')
        ->and($scenario['description'])->toBe('');
});

it('normalizes configured scenario metadata', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'secret_required' => [
            'label' => 'Secret Required Redemption',
            'category' => 'contract',
            'mode' => 'default',
            'tags' => ['voucher', 'redemption', 'validation'],
            'risk' => 'medium',
            'description' => 'Proves secret validation.',
        ],
    ]);

    $scenario = app(LifecycleScenarioRepository::class)->findOrFail('secret_required');

    expect($scenario['key'])->toBe('secret_required')
        ->and($scenario['label'])->toBe('Secret Required Redemption')
        ->and($scenario['category'])->toBe('contract')
        ->and($scenario['mode'])->toBe('default')
        ->and($scenario['tags'])->toBe(['voucher', 'redemption', 'validation'])
        ->and($scenario['risk'])->toBe('medium')
        ->and($scenario['description'])->toBe('Proves secret validation.');
});

it('finds scenarios by category', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'category' => 'smoke',
        ],
        'secret_required' => [
            'category' => 'contract',
        ],
    ]);

    $repository = app(LifecycleScenarioRepository::class);

    expect(array_keys($repository->byCategory('contract')))->toBe(['secret_required'])
        ->and(array_keys($repository->byCategory('smoke')))->toBe(['basic_cash']);
});

it('finds scenarios by tag', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'secret_required' => [
            'tags' => ['voucher', 'redemption', 'validation'],
        ],
        'wallet_debit_smoke' => [
            'tags' => ['wallet'],
        ],
    ]);

    $repository = app(LifecycleScenarioRepository::class);

    expect(array_keys($repository->byTag('wallet')))->toBe(['wallet_debit_smoke'])
        ->and(array_keys($repository->byTag('voucher')))->toBe(['secret_required']);
});

it('groups scenarios by category', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'basic_cash' => [
            'category' => 'smoke',
        ],
        'secret_required' => [
            'category' => 'contract',
        ],
        'otp_required_contract' => [
            'category' => 'contract',
        ],
    ]);

    $groups = app(LifecycleScenarioRepository::class)->groupedByCategory();

    expect($groups)->toHaveKeys(['contract', 'smoke'])
        ->and($groups['smoke'])->toHaveKey('basic_cash')
        ->and($groups['contract'])->toHaveKeys(['secret_required', 'otp_required_contract']);
});

it('throws when lifecycle scenarios config is not an array', function () {
    config()->set('x-change.lifecycle.scenarios', 'invalid');

    app(LifecycleScenarioRepository::class)->all();
})->throws(InvalidArgumentException::class, 'Lifecycle scenarios config must be an array.');

it('throws when a lifecycle scenario is not an array', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'broken' => 'invalid',
    ]);

    app(LifecycleScenarioRepository::class)->all();
})->throws(InvalidArgumentException::class, 'Lifecycle scenario [broken] must be an array.');

it('returns configured attempts when no selected attempt is provided', function () {
    $repository = app(LifecycleScenarioRepository::class);

    $scenario = [
        'attempts' => [
            'blocked' => [
                'claim' => [],
                'expect' => [
                    'status' => 'failed',
                ],
            ],
            'success' => [
                'claim' => [],
                'expect' => [
                    'status' => 'succeeded',
                ],
            ],
        ],
    ];

    $attempts = $repository->attemptsFor($scenario);

    expect($attempts)->toHaveKeys(['blocked', 'success']);
});

it('returns only selected attempt when provided', function () {
    $repository = app(LifecycleScenarioRepository::class);

    $scenario = [
        'attempts' => [
            'blocked' => [
                'claim' => [],
                'expect' => [
                    'status' => 'failed',
                ],
            ],
            'success' => [
                'claim' => [],
                'expect' => [
                    'status' => 'succeeded',
                ],
            ],
        ],
    ];

    $attempts = $repository->attemptsFor($scenario, 'success');

    expect($attempts)->toHaveCount(1)
        ->and($attempts)->toHaveKey('success');
});

it('throws when selected attempt does not exist', function () {
    app(LifecycleScenarioRepository::class)->attemptsFor(
        scenario: [
            'attempts' => [
                'success' => [],
            ],
        ],
        selectedAttempt: 'missing',
    );
})->throws(InvalidArgumentException::class, 'Unknown attempt');

it('provides a default attempt from scenario claim and expect when scenario has no attempts', function () {
    $attempts = app(LifecycleScenarioRepository::class)->attemptsFor([
        'claim' => [
            'mobile' => '639178251991',
        ],
        'expect' => [
            'status' => 'succeeded',
        ],
    ]);

    expect($attempts)->toHaveKey('default')
        ->and(data_get($attempts, 'default.claim.mobile'))->toBe('639178251991')
        ->and(data_get($attempts, 'default.expect.status'))->toBe('succeeded');
});

it('provides an empty default claim and expect when scenario has no claim expect or attempts', function () {
    $attempts = app(LifecycleScenarioRepository::class)->attemptsFor([]);

    expect($attempts)->toHaveKey('default')
        ->and(data_get($attempts, 'default.claim'))->toBe([])
        ->and(data_get($attempts, 'default.expect'))->toBe([]);
});

it('resolves mode and label defaults', function () {
    $repository = app(LifecycleScenarioRepository::class);

    expect($repository->modeFor([]))->toBe('default')
        ->and($repository->labelFor('basic_cash', []))->toBe('basic_cash');
});

it('resolves configured mode and label', function () {
    $repository = app(LifecycleScenarioRepository::class);

    $scenario = [
        'mode' => 'settlement_three_party_flow',
        'label' => 'Settlement Three Party Flow',
    ];

    expect($repository->modeFor($scenario))->toBe('settlement_three_party_flow')
        ->and($repository->labelFor('ignored', $scenario))->toBe('Settlement Three Party Flow');
});

it('preserves explicit mode in scenario metadata', function () {
    config()->set('x-change.lifecycle.scenarios', [
        'sequential_example' => [
            'mode' => 'sequential_claims',
        ],
    ]);

    $scenario = app(LifecycleScenarioRepository::class)->findOrFail('sequential_example');

    expect($scenario)->toHaveKey('mode')
        ->and($scenario['mode'])->toBe('sequential_claims');
});
