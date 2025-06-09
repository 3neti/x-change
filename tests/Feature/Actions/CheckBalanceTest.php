<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Wallet\Enums\WalletType;
use App\Services\BalanceAccessLogger;
use Illuminate\Support\Facades\App;
use App\Actions\CheckBalance;
use Brick\Money\Money;
use App\Models\User;

uses(RefreshDatabase::class);

test('check balance returns main wallet balance', function () {
    $user = User::factory()->create();
    $user->depositFloat(500); // Main/default wallet

    $balance = CheckBalance::run($user);

    expect($balance)->toBeInstanceOf(Money::class)
        ->and($balance->getAmount()->toFloat())->toBe(500.0);
});

test('check balance returns named wallet balance', function () {
    $user = User::factory()->create();
    $wallet = $user->getWallet(WalletType::ESCROW->value);

    $wallet->depositFloat(750);

    $balance = CheckBalance::run($user, WalletType::ESCROW);

    expect($balance)->toBeInstanceOf(Money::class)
        ->and($balance->getAmount()->toFloat())->toBe(750.0);
});

test('balance logger is called once during CheckBalance handle()', function () {
    // Arrange
    $user = User::factory()->create();

    // Mock the logger
    $mock = Mockery::mock(BalanceAccessLogger::class);
    App::instance(BalanceAccessLogger::class, $mock);

    // Expect logger to be called once with correct parameters
    $mock->shouldReceive('log')
        ->once()
        ->withArgs(function ($passedUser, $passedFloat, $passedWalletType) use ($user) {
            return $passedUser->is($user)
                && is_float($passedFloat)
                && ($passedWalletType === null || $passedWalletType instanceof WalletType);
        });

    // Act
    CheckBalance::run($user);
});
