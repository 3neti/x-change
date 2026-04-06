<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use RuntimeException;

class DefaultWalletProvisioningService implements WalletProvisioningContract
{
    public function open(mixed $issuer, array $input): mixed
    {
        if (! is_object($issuer) || ! method_exists($issuer, 'wallet')) {
            throw new RuntimeException('Issuer does not support wallet provisioning.');
        }

        $slug = (string) (data_get($input, 'wallet.slug')
            ?? config('x-change.onboarding.default_wallet_slug', 'platform'));

        $name = (string) (data_get($input, 'wallet.name')
            ?? config('x-change.onboarding.default_wallet_name', 'Platform Wallet'));

        $wallet = $issuer->wallet()->where('slug', $slug)->first();

        if ($wallet) {
            return $wallet;
        }

        $walletModel = $issuer->wallet()->make([
            'name' => $name,
            'slug' => $slug,
        ]);

        $walletModel->save();

        return $walletModel->fresh() ?? $walletModel;
    }
}
