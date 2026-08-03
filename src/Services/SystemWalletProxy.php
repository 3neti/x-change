<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use RuntimeException;

class SystemWalletProxy implements WalletProxy
{
    public function __construct(
        private readonly SystemUserResolverContract $systemUsers,
    ) {}

    public function resolve(): Wallet
    {
        $user = $this->systemUsers->resolve();
        $walletSlug = $this->walletSlug();

        $wallet = $this->resolveWalletFromUser($user, $walletSlug);

        if (! $wallet instanceof Wallet) {
            throw new RuntimeException(sprintf(
                'System wallet with slug [%s] could not be resolved.',
                $walletSlug
            ));
        }

        return $wallet;
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
