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

    public function handle(
        User $user,
        WalletType|null $walletType = null
    ): Money
    {
        $slug = is_null($walletType) ? WalletType::default()->value : $walletType->value;
        $wallet = $user->getWallet($slug);
        $float = (float) $wallet->balanceFloat;

        $balance = Money::of($float, Number::defaultCurrency());

        $requestor = auth()->user();
        if (!$requestor instanceof \Illuminate\Database\Eloquent\Model) {
            throw new \RuntimeException('Authenticated user is not a valid Eloquent model.');
        }

        $this->logger->log($wallet, $balance, $requestor);

        return $balance;
    }
}
