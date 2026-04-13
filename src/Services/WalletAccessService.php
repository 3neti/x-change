<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use RuntimeException;

class WalletAccessService implements WalletAccessContract
{
    public function resolveForUser(mixed $user): mixed
    {
        if (! is_object($user)) {
            throw new PayCodeWalletNotResolved('Issuer wallet was not found.');
        }

        $wallet = null;

        if (method_exists($user, 'getWallet')) {
            $wallet = $user->getWallet('platform');
        }

        if (! $wallet && isset($user->wallet)) {
            $wallet = $user->wallet;
        }

        if (! $wallet && method_exists($user, 'wallets')) {
            $wallet = $user->wallets()->where('slug', 'platform')->first()
                ?? $user->wallets()->first();
        }

        if (! $wallet) {
            throw new PayCodeWalletNotResolved('Issuer wallet was not found.');
        }

        return $wallet;
    }

    public function getBalance(mixed $wallet): int|float|string
    {
        if (! is_object($wallet)) {
            throw new RuntimeException('Wallet balance could not be determined.');
        }

        // Prefer integer/minor-unit balance when available.
        if (isset($wallet->balanceInt)) {
            return (int) $wallet->balanceInt;
        }

        if (method_exists($wallet, 'getBalanceIntAttribute')) {
            return (int) $wallet->getBalanceIntAttribute();
        }

        if (isset($wallet->balance)) {
            return $wallet->balance;
        }

        if (isset($wallet->balanceFloat)) {
            return $wallet->balanceFloat;
        }

        if (method_exists($wallet, 'getBalanceAttribute')) {
            return $wallet->getBalanceAttribute();
        }

        throw new RuntimeException('Wallet balance could not be determined.');
    }

    public function assertCanAfford(mixed $wallet, float|int|string $amount): void
    {
        $balance = $this->normalizeBalanceForComparison($this->getBalance($wallet));
        $required = $this->normalizeAmountForWallet($amount);

        if ($balance < $required) {
            throw new InsufficientWalletBalance(sprintf(
                'Issuer wallet cannot afford the requested amount. Balance: %s, Required: %s',
                $balance,
                $required,
            ));
        }
    }

    public function debit(mixed $wallet, float|int|string $amount, array $meta = []): mixed
    {
        if (! is_object($wallet)) {
            throw new RuntimeException('Wallet does not support debit/withdraw operations.');
        }

        $this->assertCanAfford($wallet, $amount);

        $normalizedAmount = $this->normalizeAmountForWallet($amount);

        if (method_exists($wallet, 'withdraw')) {
            return $wallet->withdraw($normalizedAmount, $meta);
        }

        if (method_exists($wallet, 'forceWithdraw')) {
            return $wallet->forceWithdraw($normalizedAmount, $meta);
        }

        throw new RuntimeException('Wallet does not support debit/withdraw operations.');
    }

    protected function normalizeAmountForWallet(float|int|string $amount): int|string
    {
        if (is_int($amount)) {
            return $amount;
        }

        if (is_string($amount)) {
            $trimmed = trim($amount);

            // Integer strings can pass through directly.
            if (preg_match('/^-?\d+$/', $trimmed) === 1) {
                return $trimmed;
            }

            $amount = (float) $trimmed;
        }

        // Convert decimal major units (e.g. 20.50 PHP) to minor units (2050).
        return (int) round(((float) $amount) * 100);
    }

    protected function normalizeBalanceForComparison(int|float|string $balance): int
    {
        if (is_int($balance)) {
            return $balance;
        }

        if (is_string($balance)) {
            $trimmed = trim($balance);

            if (preg_match('/^-?\d+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }

            return (int) round(((float) $trimmed) * 100);
        }

        return (int) round($balance * 100);
    }
}
