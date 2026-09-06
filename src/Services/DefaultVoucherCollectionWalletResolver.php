<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
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

        $wallet = Wallet::query()->find($walletId);

        if (! $wallet instanceof Wallet) {
            return null;
        }

        $issuer = $this->resolveIssuerModel($voucher);

        if (! $issuer instanceof Model) {
            return $wallet;
        }

        return $wallet->holder_type === $issuer->getMorphClass()
            && (string) $wallet->holder_id === (string) $issuer->getKey()
                ? $wallet
                : null;
    }

    protected function resolveIssuerWallet(Voucher $voucher): ?Wallet
    {
        $issuer = $this->resolveIssuerModel($voucher);

        if (! $issuer instanceof Model) {
            return null;
        }

        try {
            $wallet = app(WalletAccessContract::class)->resolveForUser($issuer);
        } catch (PayCodeWalletNotResolved) {
            return null;
        }

        return $wallet instanceof Wallet
            ? $wallet
            : null;
    }

    private function resolveIssuerModel(Voucher $voucher): ?Model
    {
        $issuerId = data_get($voucher->metadata, 'instructions.metadata.issuer_id')
            ?? data_get($voucher->metadata, 'instructions.metadata.metadata.issuer_id')
            ?? data_get($voucher->metadata, 'issuer_id');

        if (! $issuerId) {
            return null;
        }

        /*
         * Issuer evidence authorizes explicit collection-wallet ownership and
         * supports legacy vouchers issued before that wallet became mandatory.
         */
        $issuerModel = config('x-change.lifecycle.defaults.user_model')
            ?: config('x-change.onboarding.issuer_model');

        if (! is_string($issuerModel) || $issuerModel === '' || ! class_exists($issuerModel)) {
            return null;
        }

        $issuer = $issuerModel::query()->find($issuerId);

        if (! $issuer instanceof Model) {
            return null;
        }

        return $issuer;
    }
}
