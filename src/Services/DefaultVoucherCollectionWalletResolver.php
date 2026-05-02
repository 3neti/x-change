<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;

class DefaultVoucherCollectionWalletResolver implements VoucherCollectionWalletResolverContract
{
    public function resolve(Voucher $voucher): Wallet
    {
        if ($wallet = $this->resolveExplicitCollectionWallet($voucher)) {
            return $wallet;
        }

        if ($wallet = $this->resolveIssuerWallet($voucher)) {
            return $wallet;
        }

        throw new PayCodeWalletNotResolved('Unable to resolve collection wallet for voucher.');
    }

    protected function resolveExplicitCollectionWallet(Voucher $voucher): ?Wallet
    {
        $walletId = data_get($voucher->metadata, 'instructions.metadata.collection_wallet_id')
            ?? data_get($voucher->metadata, 'collection_wallet_id');

        if (! $walletId) {
            return null;
        }

        return Wallet::query()->find($walletId);
    }

    protected function resolveIssuerWallet(Voucher $voucher): ?Wallet
    {
        $issuerId = data_get($voucher->metadata, 'instructions.metadata.issuer_id')
            ?? data_get($voucher->metadata, 'instructions.metadata.metadata.issuer_id')
            ?? data_get($voucher->metadata, 'issuer_id');

        if (! $issuerId) {
            return null;
        }

        $issuerModel = config('x-change.lifecycle.defaults.user_model')
            ?: config('x-change.onboarding.issuer_model');

        if (! is_string($issuerModel) || $issuerModel === '' || ! class_exists($issuerModel)) {
            return null;
        }

        $issuer = $issuerModel::query()->find($issuerId);

        if (! $issuer instanceof Model) {
            return null;
        }

        $wallet = $issuer->wallet;

        return $wallet instanceof Wallet
            ? $wallet
            : null;
    }
}
