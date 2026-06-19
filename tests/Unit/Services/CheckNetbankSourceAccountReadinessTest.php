<?php

declare(strict_types=1);

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\XChange\Services\CheckNetbankSourceAccountReadiness;

it('normalizes the NetBank source account balance response', function () {
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', true);
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number', '113-001-00001-9');

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')
        ->once()
        ->with('113-001-00001-9')
        ->andReturn([
            'balance' => 500000,
            'available_balance' => 450000,
            'currency' => 'PHP',
            'as_of' => '2026-06-19T10:00:00+08:00',
            'raw' => [],
        ]);

    $readiness = (new CheckNetbankSourceAccountReadiness($gateway))->handle(150000);

    expect($readiness['enabled'])->toBeTrue()
        ->and($readiness['ready'])->toBeTrue()
        ->and($readiness['checked'])->toBeTrue()
        ->and($readiness['balance_minor'])->toBe(500000)
        ->and($readiness['available_balance_minor'])->toBe(450000)
        ->and($readiness['account_number_masked'])->toBe('***********01-9')
        ->and($readiness['message'])->toBe('NetBank source account has enough available balance.');
});

it('marks the NetBank source account unready when available balance is insufficient', function () {
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', true);
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number', '113-001-00001-9');

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')
        ->once()
        ->with('113-001-00001-9')
        ->andReturn([
            'balance' => 500000,
            'available_balance' => 100000,
            'currency' => 'PHP',
            'as_of' => '2026-06-19T10:00:00+08:00',
            'raw' => [],
        ]);

    $readiness = (new CheckNetbankSourceAccountReadiness($gateway))->handle(150000);

    expect($readiness['enabled'])->toBeTrue()
        ->and($readiness['ready'])->toBeFalse()
        ->and($readiness['checked'])->toBeTrue()
        ->and($readiness['available_balance_minor'])->toBe(100000)
        ->and($readiness['message'])->toBe('NetBank source account cannot cover the requested amount.');
});

it('normalizes decimal NetBank source account balance values for display checks', function () {
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', true);
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number', '113-001-00001-9');

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')
        ->once()
        ->with('113-001-00001-9')
        ->andReturn([
            'balance' => '683.89',
            'available_balance' => '683.89',
            'currency' => 'PHP',
            'as_of' => '2026-06-19T10:00:00+08:00',
            'raw' => [],
        ]);

    $readiness = (new CheckNetbankSourceAccountReadiness($gateway))->handle();

    expect($readiness['enabled'])->toBeTrue()
        ->and($readiness['ready'])->toBeTrue()
        ->and($readiness['checked'])->toBeTrue()
        ->and($readiness['balance_minor'])->toBe(68389)
        ->and($readiness['available_balance_minor'])->toBe(68389)
        ->and($readiness['message'])->toBe('NetBank source account balance was refreshed.');
});

it('normalizes nested NetBank raw balance values when adapter values are missing', function () {
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', true);
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number', '113-001-00001-9');

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')
        ->once()
        ->with('113-001-00001-9')
        ->andReturn([
            'as_of' => '2026-06-19T10:00:00+08:00',
            'raw' => [
                'balance' => ['cur' => 'PHP', 'num' => '68389'],
                'available_balance' => ['cur' => 'PHP', 'num' => '68389'],
            ],
        ]);

    $readiness = (new CheckNetbankSourceAccountReadiness($gateway))->handle();

    expect($readiness['currency'])->toBe('PHP')
        ->and($readiness['balance_minor'])->toBe(68389)
        ->and($readiness['available_balance_minor'])->toBe(68389);
});

it('does not treat a failed NetBank adapter balance response as a fresh zero balance', function () {
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', true);
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number', '113-001-00001-9');

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')
        ->once()
        ->with('113-001-00001-9')
        ->andReturn([
            'balance' => 0,
            'available_balance' => 0,
            'currency' => 'PHP',
            'as_of' => null,
            'raw' => [],
        ]);

    $readiness = (new CheckNetbankSourceAccountReadiness($gateway))->handle();

    expect($readiness['enabled'])->toBeTrue()
        ->and($readiness['ready'])->toBeFalse()
        ->and($readiness['checked'])->toBeTrue()
        ->and($readiness['reason'])->toBe('balance_unavailable')
        ->and($readiness)->not->toHaveKey('available_balance_minor')
        ->and($readiness['message'])->toBe('NetBank source account balance check failed.');
});
