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

    expect($repository->findOrFail($key))->toBeArray();
});

it('throws when lifecycle scenario key does not exist', function () {
    app(LifecycleScenarioRepository::class)
        ->findOrFail('missing_scenario_key');
})->throws(InvalidArgumentException::class, 'Unknown scenario');

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
