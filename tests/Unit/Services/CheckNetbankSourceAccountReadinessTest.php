<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderSourceAccountReadiness;
use LBHurtado\EmiCore\Data\Providers\ProviderSourceAccountReadinessRequestData;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Support\NetbankSourceAccountReadiness;
use LBHurtado\XChange\Services\CheckNetbankSourceAccountReadiness;
use LBHurtado\XChange\Services\CheckProviderSourceAccountReadiness;

it('accepts a provider-neutral readiness capability', function (): void {
    $source = new class implements ProviderSourceAccountReadiness
    {
        public function providerCode(): string
        {
            return 'netbank';
        }

        public function inspectSourceAccount(ProviderSourceAccountReadinessRequestData $request): ?array
        {
            return ['enabled' => true, 'checked' => true, 'ready' => $request->requiredAmountMinor === 500];
        }
    };

    expect((new CheckNetbankSourceAccountReadiness($source))->handle(500))->toMatchArray(['enabled' => true, 'checked' => true, 'ready' => true]);
});

it('fails closed without exposing a provider exception', function (): void {
    $source = Mockery::mock(ProviderSourceAccountReadiness::class);
    $source->shouldReceive('providerCode')->once()->andReturn('netbank');
    $source->shouldReceive('inspectSourceAccount')->once()->andThrow(new RuntimeException('private provider data'));

    $result = (new CheckNetbankSourceAccountReadiness($source))->handle(500);
    expect($result)->toMatchArray(['ready' => false, 'reason' => 'balance_unavailable'])
        ->and(json_encode($result))->not->toContain('private provider data');
});

it('fails closed when an enabled provider has no readiness capability', function (): void {
    config()->set('x-change.provider_runtime.providers.missing_provider.source_account_readiness.enabled', true);

    $result = (new CheckProviderSourceAccountReadiness)->handle(500, 'missing_provider');
    expect($result)->toMatchArray(['enabled' => true, 'ready' => false, 'checked' => false]);
});

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

    $readiness = (new CheckNetbankSourceAccountReadiness(new NetbankSourceAccountReadiness($gateway)))->handle(150000);

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

    $readiness = (new CheckNetbankSourceAccountReadiness(new NetbankSourceAccountReadiness($gateway)))->handle(150000);

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

    $readiness = (new CheckNetbankSourceAccountReadiness(new NetbankSourceAccountReadiness($gateway)))->handle();

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

    $readiness = (new CheckNetbankSourceAccountReadiness(new NetbankSourceAccountReadiness($gateway)))->handle();

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

    $readiness = (new CheckNetbankSourceAccountReadiness(new NetbankSourceAccountReadiness($gateway)))->handle();

    expect($readiness['enabled'])->toBeTrue()
        ->and($readiness['ready'])->toBeFalse()
        ->and($readiness['checked'])->toBeTrue()
        ->and($readiness['reason'])->toBe('balance_unavailable')
        ->and($readiness)->not->toHaveKey('available_balance_minor')
        ->and($readiness['message'])->toBe('NetBank source account balance check failed.');
});
