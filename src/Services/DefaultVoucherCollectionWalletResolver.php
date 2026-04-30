<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use RuntimeException;

class DefaultVoucherCollectionWalletResolver implements VoucherCollectionWalletResolverContract
{
    public function resolve(Voucher $voucher): Wallet
    {
        $issuerId = data_get($voucher->metadata, 'instructions.metadata.issuer_id');

        if (blank($issuerId)) {
            throw new RuntimeException('Voucher issuer could not be resolved for collection.');
        }

        $userModel = (string) config('x-change.lifecycle.defaults.user_model');

        if ($userModel === '' || ! class_exists($userModel)) {
            $userModel = (string) config('x-change.onboarding.issuer_model');
        }

        if ($userModel === '' || ! class_exists($userModel)) {
            throw new RuntimeException('Collection wallet user model is not configured.');
        }

        /** @var Model|null $issuer */
        $issuer = $userModel::query()->find($issuerId);

        if (! $issuer || ! property_exists($issuer, 'wallet') && ! method_exists($issuer, 'getAttribute')) {
            throw new RuntimeException('Voucher issuer wallet could not be resolved.');
        }

        $wallet = $issuer->wallet;

        if (! $wallet instanceof Wallet) {
            throw new RuntimeException('Resolved issuer does not expose a valid wallet.');
        }

        return $wallet;
    }
}
