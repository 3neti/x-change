<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;

class WalletAccessService implements WalletAccessContract
{
    public function resolveForUser(mixed $user): mixed
    {
        if (! is_object($user) || ! method_exists($user, 'wallet')) {
            throw new PayCodeWalletNotResolved('Unable to resolve wallet for issuer.');
        }

        $wallet = $user->wallet()->where('slug', 'platform')->first()
            ?? $user->wallet()->first();

        if (! $wallet) {
            throw new PayCodeWalletNotResolved('Issuer wallet was not found.');
        }

        return $wallet;
    }

    public function getBalance(mixed $wallet): int|float|string
    {
        return $wallet->balance ?? 0;
    }

    public function assertCanAfford(mixed $wallet, int|float|string $amount): void
    {
        $balance = (float) $this->getBalance($wallet);
        $required = (float) $amount;

        if ($balance < $required) {
            throw new InsufficientWalletBalance(sprintf(
                'Wallet balance is insufficient. Required: %s, Available: %s',
                (string) $required,
                (string) $balance
            ));
        }
    }

    public function debit(mixed $wallet, int|float|string $amount, array $meta = []): mixed
    {
        if (! $wallet instanceof Wallet && ! method_exists($wallet, 'withdraw')) {
            throw new PayCodeWalletNotResolved('Resolved wallet does not support debit operations.');
        }

        return $wallet->withdraw((int) round((float) $amount), $meta);
    }
}
