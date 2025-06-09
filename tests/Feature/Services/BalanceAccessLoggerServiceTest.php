<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Wallet\Enums\WalletType;
use App\Services\BalanceAccessLogger;
use App\Models\BalanceAccessLog;
use Brick\Money\Money;
use App\Models\User;

uses(RefreshDatabase::class);

test('it logs balance access with wallet and requestor', function () {
    // Arrange
    $user = User::factory()->create();
    $wallet = $user->wallet; // Bavix wallet model
    $balance = Money::of(123.45, 'PHP');

    // Act
    app(BalanceAccessLogger::class)->log($wallet, $balance, $user);

    // Assert
    $log = BalanceAccessLog::first();

    expect($log)->not()->toBeNull()
        ->and($log->wallet->id)->toBe($wallet->id)
        ->and($log->wallet_type)->toBe($wallet->getMorphClass())
        ->and($log->requestor->id)->toBe($user->id)
        ->and($log->requestor_type)->toBe($user->getMorphClass())
        ->and((string) $log->balance->getAmount())->toBe('123.45')
        ->and($log->balance->getCurrency()->getCurrencyCode())->toBe('PHP');
});
