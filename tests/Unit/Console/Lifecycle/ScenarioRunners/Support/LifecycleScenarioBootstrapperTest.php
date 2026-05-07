<?php

declare(strict_types=1);

use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioBootstrapper;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

it('builds lifecycle input from scenario values', function () {
    $bootstrapper = app(LifecycleScenarioBootstrapper::class);

    $input = $bootstrapper->buildLifecycleInput(
        scenario: [
            'currency' => 'PHP',
            'cash' => [
                'validation' => [
                    'secret' => 'secret123',
                    'mobile' => '639178251991',
                    'country' => 'PH',
                ],
                'settlement_rail' => 'INSTAPAY',
                'fee_strategy' => 'absorb',
            ],
            'inputs' => [
                'fields' => ['signature'],
            ],
            'prefix' => 'TEST',
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
        ],
        issuerId: 1,
        walletId: 1,
        amount: 100.0,
        idempotencyKey: 'lifecycle-test-key',
    );

    expect($input['issuer_id'])->toBe(1)
        ->and($input['wallet_id'])->toBe(1)
        ->and(data_get($input, 'cash.amount'))->toBe(100.0)
        ->and(data_get($input, 'cash.currency'))->toBe('PHP')
        ->and(data_get($input, 'cash.validation.secret'))->toBe('secret123')
        ->and(data_get($input, 'inputs.fields'))->toBe(['signature'])
        ->and(data_get($input, 'metadata.flow_type'))->toBe('disbursable')
        ->and(data_get($input, '_meta.idempotency_key'))->toBe('lifecycle-test-key');
});

it('resolves max polls from explicit option', function () {
    $bootstrapper = app(LifecycleScenarioBootstrapper::class);

    expect($bootstrapper->resolveMaxPolls(180, 10, '3'))->toBe(3);
});

it('resolves max polls from timeout and poll when explicit option is missing', function () {
    $bootstrapper = app(LifecycleScenarioBootstrapper::class);

    expect($bootstrapper->resolveMaxPolls(180, 10))->toBe(18)
        ->and($bootstrapper->resolveMaxPolls(181, 10))->toBe(19);
});

it('bootstraps a lifecycle scenario end to end', function () {
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);

    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'Lifecycle Issuer',
        'email' => 'issuer@example.test',
        'password' => bcrypt('password'),
    ]);

    $issuer->setMobileChannel('09171234567');
    $issuer->save();

    fundTestUserWallet($issuer);

    $scenario = config('x-change.lifecycle.scenarios.basic_cash');

    $bootstrap = app(LifecycleScenarioBootstrapper::class)->bootstrap(
        scenario: array_replace_recursive(
            (array) config('x-change.lifecycle.defaults', []),
            (array) $scenario,
            [
                'issuer_id' => $issuer->getKey(),
                'wallet_id' => $issuer->getKey(),
                'mobile' => $issuer->getMobileChannel(),
            ],
        ),
    );

    expect($bootstrap->issuer)->not->toBeNull()
        ->and($bootstrap->baseClaimMobile)->not->toBe('')
        ->and($bootstrap->estimate)->toBeArray()
        ->and($bootstrap->generated->code)->not->toBe('')
        ->and($bootstrap->voucher)->not->toBeNull();
});
