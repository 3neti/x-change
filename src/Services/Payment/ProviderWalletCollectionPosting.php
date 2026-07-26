<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherCollectionPostingContract;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Data\Payment\VoucherCollectionPostingData;

final readonly class ProviderWalletCollectionPosting implements VoucherCollectionPostingContract
{
    public function __construct(
        private VoucherCollectionWalletResolverContract $wallets,
    ) {}

    public function driver(): string
    {
        return 'provider_wallet';
    }

    public function post(
        Voucher $voucher,
        ConfirmedVoucherCollectionData $collection,
    ): VoucherCollectionPostingData {
        $transaction = $this->wallets->resolve($voucher)->deposit(
            $collection->amountMinor,
            [
                'reason' => 'voucher_collection',
                'voucher_code' => $voucher->code,
                'provider' => $collection->provider,
                'provider_reference' => $collection->providerReference,
                'provider_transaction_id' => $collection->providerTransactionId,
                'payer' => [
                    'name' => $collection->payerName,
                    'mobile' => $collection->payerMobile,
                ],
                'meta' => $collection->metadata,
            ],
            true,
        );

        return new VoucherCollectionPostingData(
            walletTransactionId: (int) $transaction->getKey(),
            metadata: [
                'provider' => $collection->provider,
                'provider_reference' => $collection->providerReference,
                'provider_transaction_id' => $collection->providerTransactionId,
            ],
        );
    }
}
