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
    $this->actingAs($user);
    $user->depositFloat(500); // Main/default wallet

    $balance = CheckBalance::run($user);

    expect($balance)->toBeInstanceOf(Money::class)
        ->and($balance->getAmount()->toFloat())->toBe(500.0);
});

test('check balance returns named wallet balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = $user->getWallet(WalletType::ESCROW->value);

    $wallet->depositFloat(750);

    $balance = CheckBalance::run($user, WalletType::ESCROW);

    expect($balance)->toBeInstanceOf(Money::class)
        ->and($balance->getAmount()->toFloat())->toBe(750.0);
});

test('balance logger is called once during CheckBalance handle()', function () {
    // Arrange
    $user = User::factory()->create();
    $this->actingAs($user);

    // Mock the logger and bind it into the container
    $mock = Mockery::mock(BalanceAccessLogger::class);
    App::instance(BalanceAccessLogger::class, $mock);

    // Expect logger to be called once with correct parameters
    $mock->shouldReceive('log')
        ->once()
        ->withArgs(function ($wallet, $balance, $requestor) use ($user) {
            return $wallet->holder->is($user) &&
                $balance instanceof Money &&
                $requestor->is($user);
        });

    // Act
    CheckBalance::run($user);
});
