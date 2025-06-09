<?php

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Wallet\Enums\WalletType;
use App\Services\BalanceAccessLogger;
use Illuminate\Support\Number;
use Brick\Money\Money;
use App\Models\User;

class CheckBalance
{
    use AsAction;

    public function __construct(
        protected BalanceAccessLogger $logger
    ) {}

    public function handle(User $user, WalletType $walletType = null): Money
    {
        $float = (float) is_null($walletType)
            ? $user->balanceFloat
            : $user->getWallet($walletType->value)->balanceFloat;

        $this->logger->log($user, $float, $walletType);

        return Money::of($float, Number::defaultCurrency());
    }
}
