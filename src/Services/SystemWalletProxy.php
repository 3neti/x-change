<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use RuntimeException;

class SystemWalletProxy implements WalletProxy
{
    public function resolve(): Wallet
    {
        $modelClass = $this->systemUserModelClass();
        $systemUserId = $this->systemUserId();
        $walletSlug = $this->walletSlug();

        $user = $modelClass::query()->find($systemUserId);

        if (! $user) {
            throw new RuntimeException(sprintf(
                'Configured system user [%s] was not found.',
                (string) $systemUserId
            ));
        }

        $wallet = $this->resolveWalletFromUser($user, $walletSlug);

        if (! $wallet instanceof Wallet) {
            throw new RuntimeException(sprintf(
                'System wallet with slug [%s] could not be resolved.',
                $walletSlug
            ));
        }

        return $wallet;
    }

    protected function systemUserModelClass(): string
    {
        $modelClass = config('x-change.onboarding.issuer_model');

        if (! is_string($modelClass) || $modelClass === '') {
            throw new RuntimeException('No system user model configured.');
        }

        if (! class_exists($modelClass)) {
            throw new RuntimeException(sprintf(
                'Configured system user model [%s] does not exist.',
                $modelClass
            ));
        }

        return $modelClass;
    }

    protected function systemUserId(): int|string
    {
        $systemUserId = config('x-change.payout.system_user_id');

        if ($systemUserId === null || $systemUserId === '') {
            throw new RuntimeException('No payout system user ID configured.');
        }

        return $systemUserId;
    }

    protected function walletSlug(): string
    {
        $slug = config(
            'x-change.payout.system_wallet_slug',
            config('x-change.onboarding.default_wallet_slug', 'platform')
        );

        return is_string($slug) && $slug !== ''
            ? $slug
            : 'platform';
    }

    protected function resolveWalletFromUser(mixed $user, string $walletSlug): ?Wallet
    {
        if (is_object($user) && method_exists($user, 'getWallet')) {
            $wallet = $user->getWallet($walletSlug);

            if ($wallet instanceof Wallet) {
                return $wallet;
            }
        }

        if (is_object($user) && isset($user->wallet) && $user->wallet instanceof Wallet) {
            return $user->wallet;
        }

        if (is_object($user) && method_exists($user, 'wallet')) {
            $wallet = $user->wallet()->where('slug', $walletSlug)->first()
                ?? $user->wallet()->first();

            if ($wallet instanceof Wallet) {
                return $wallet;
            }
        }

        return null;
    }
}
