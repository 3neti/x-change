<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;

it('generates a pay code by resolving issuer, estimating cost, debiting wallet, and issuing voucher', function () {
    $issuer = (object) ['id' => 1, 'name' => 'Issuer'];
    $wallet = (object) ['id' => 10, 'balance' => 1000];

    $input = [
        'cash' => [
            'amount' => 100.0,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => ['selfie'],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/webhook',
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
    ];

    $estimate = [
        'currency' => 'PHP',
        'base_fee' => 1.0,
        'components' => [
            'kyc' => 25.0,
            'selfie' => 5.0,
        ],
        'total' => 31.0,
    ];

    $issued = [
        'voucher_id' => 99,
        'code' => 'TEST-1234',
        'amount' => 100.0,
        'currency' => 'PHP',
    ];

    $debit = (object) ['id' => 501];

    $users = Mockery::mock(UserResolverContract::class);
    $users->shouldReceive('resolve')
        ->once()
        ->with($input)
        ->andReturn($issuer);

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('resolveForUser')
        ->once()
        ->with($issuer)
        ->andReturn($wallet);

    $wallets->shouldReceive('getBalance')
        ->once()
        ->with($wallet)
        ->andReturn(1000.0);

    $wallets->shouldReceive('assertCanAfford')
        ->once()
        ->with($wallet, 31.0)
        ->andReturnNull();

    $wallets->shouldReceive('debit')
        ->once()
        ->with($wallet, 31.0, Mockery::on(function (array $meta) use ($estimate) {
            return $meta['reason'] === 'pay_code_issuance'
                && $meta['cost'] === $estimate;
        }))
        ->andReturn($debit);

    $wallets->shouldReceive('getBalance')
        ->once()
        ->with($wallet)
        ->andReturn(969.0);

    $estimateAction = Mockery::mock(EstimatePayCodeCost::class);
    $estimateAction->shouldReceive('handle')
        ->once()
        ->with($input)
        ->andReturn($estimate);

    $issuance = Mockery::mock(PayCodeIssuanceContract::class);
    $issuance->shouldReceive('issue')
        ->once()
        ->with($issuer, $input)
        ->andReturn($issued);

    $action = new GeneratePayCode(
        $users,
        $wallets,
        $estimateAction,
        $issuance,
    );

    $result = $action->handle($input);

    expect($result['voucher_id'])->toBe(99);
    expect($result['code'])->toBe('TEST-1234');
    expect($result['cost'])->toBe($estimate);
    expect($result['wallet']['balance_before'])->toBe(1000.0);
    expect($result['wallet']['balance_after'])->toBe(969.0);
    expect($result['debit'])->toBe($debit);
});

it('throws when issuer cannot be resolved', function () {
    $input = ['cash' => ['amount' => 100.0, 'currency' => 'PHP']];

    $users = Mockery::mock(UserResolverContract::class);
    $users->shouldReceive('resolve')
        ->once()
        ->with($input)
        ->andReturn(null);

    $wallets = Mockery::mock(WalletAccessContract::class);
    $estimateAction = Mockery::mock(EstimatePayCodeCost::class);
    $issuance = Mockery::mock(PayCodeIssuanceContract::class);

    $action = new GeneratePayCode(
        $users,
        $wallets,
        $estimateAction,
        $issuance,
    );

    expect(fn () => $action->handle($input))
        ->toThrow(PayCodeIssuerNotResolved::class, 'Unable to resolve Pay Code issuer.');
});

it('stops before issuance when wallet cannot afford the estimated cost', function () {
    $issuer = (object) ['id' => 1];
    $wallet = (object) ['id' => 10];

    $input = [
        'cash' => [
            'amount' => 100.0,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => ['selfie'],
        ],
        'feedback' => [],
        'rider' => [],
    ];

    $estimate = [
        'currency' => 'PHP',
        'base_fee' => 1.0,
        'components' => [],
        'total' => 999.0,
    ];

    $users = Mockery::mock(UserResolverContract::class);
    $users->shouldReceive('resolve')
        ->once()
        ->with($input)
        ->andReturn($issuer);

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('resolveForUser')
        ->once()
        ->with($issuer)
        ->andReturn($wallet);

    $wallets->shouldReceive('getBalance')
        ->once()
        ->with($wallet)
        ->andReturn(100.0);

    $wallets->shouldReceive('assertCanAfford')
        ->once()
        ->with($wallet, 999.0)
        ->andThrow(new InsufficientWalletBalance('Insufficient balance.'));

    $estimateAction = Mockery::mock(EstimatePayCodeCost::class);
    $estimateAction->shouldReceive('handle')
        ->once()
        ->with($input)
        ->andReturn($estimate);

    $issuance = Mockery::mock(PayCodeIssuanceContract::class);
    $issuance->shouldNotReceive('issue');

    $action = new GeneratePayCode(
        $users,
        $wallets,
        $estimateAction,
        $issuance,
    );

    expect(fn () => $action->handle($input))
        ->toThrow(InsufficientWalletBalance::class, 'Insufficient balance.');
});
