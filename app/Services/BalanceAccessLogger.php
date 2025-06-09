<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\BalanceAccessLog;
use Brick\Money\Money;

class BalanceAccessLogger
{
    /**
     * Log a wallet balance access event.
     *
     * @param Model $wallet        The wallet model instance (morph target)
     * @param Money $balance       The balance at the time of access
     * @param Model $requestor     The actor who triggered the access (morph target)
     */
    public function log(Model $wallet, Money $balance, Model $requestor): void
    {
        BalanceAccessLog::create([
            'wallet'  => $wallet,
            'balance' => $balance,
            'requestor' => $requestor,
            'accessed_at'  => now(),
        ]);
    }
}
