<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Contracts\Auth\Authenticatable;

class WalletResolver
{
    public function resolveForCollection(
        Voucher $voucher,
        Authenticatable $user
    ): Wallet {
        // For now: collector = authenticated user
        return $user->wallet;
    }

    public function resolveForDisbursement(
        Voucher $voucher
    ): Wallet {
        // For now: issuer wallet
        return $voucher->issuer->wallet;
    }
}
