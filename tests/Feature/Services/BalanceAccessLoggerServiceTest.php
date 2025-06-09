<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Wallet\Enums\WalletType;
use App\Services\BalanceAccessLogger;
use App\Models\BalanceAccessLog;
use Brick\Money\Money;
use App\Models\User;

uses(RefreshDatabase::class);

test('it logs balance access', function () {
    // Arrange
    $user = User::factory()->create();
    $walletType = WalletType::PLATFORM;
    $balance = Money::of(123.45, 'PHP');

    // Act
    app(BalanceAccessLogger::class)->log($user, $balance->getAmount()->toFloat(), $walletType);

    // Assert
    $log = BalanceAccessLog::first();

    expect($log)->not()->toBeNull();
    expect($log->user_id)->toBe($user->id);
    expect($log->wallet_type)->toBe($walletType->value);
    expect($log->balance)->toBe(123.45);
});
