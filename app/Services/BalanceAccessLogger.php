<?php

namespace App\Services;

use LBHurtado\Wallet\Enums\WalletType;
use App\Models\BalanceAccessLog;
use App\Models\User;

class BalanceAccessLogger
{
    public function log(User $user, float $balance, ?WalletType $walletType = null): void
    {
        BalanceAccessLog::create([
            'user_id' => $user->id,
            'wallet_type' => $walletType?->value,
            'balance' => $balance,
            'accessed_by' => request()?->ip(), // Or Auth::id(), user-agent, etc.
        ]);
    }
}
