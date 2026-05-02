<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Contracts\VoucherPaymentConfirmationContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Services\VoucherCapabilityGuard;
use LBHurtado\XChange\Services\VoucherCollectionIdempotencyService;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;

class CollectVoucherFunds
{
    public function __construct(
        protected VoucherCapabilityGuard $guard,
        protected VoucherPaymentConfirmationContract $confirmation,
        protected RecordVoucherCollection $collections,
        protected VoucherCollectionIdempotencyService $idempotency,
        protected VoucherCollectionProgressService $progress,
        protected VoucherCollectionWalletResolverContract $wallets,
    ) {}

    public function handle(Voucher $voucher, array $payload): VoucherPaymentResultData
    {
        $this->guard->ensureCanCollect($voucher);

        if ($replay = $this->idempotency->findReplay($voucher, $payload)) {
            return $replay;
        }

        $wallet = $this->wallets->resolve($voucher);

        $result = $this->confirmation->confirm($voucher, $payload);

        if (! $result->succeeded()) {
            $this->collections->handle(
                voucher: $voucher,
                result: $result,
                payload: $payload,
            );

            return $result;
        }

        return DB::transaction(function () use ($voucher, $wallet, $payload, $result): VoucherPaymentResultData {
            $transaction = $wallet->depositFloat($result->amount, [
                'reason' => 'voucher_collection',
                'voucher_code' => $voucher->code,
                'provider' => $result->provider,
                'provider_reference' => $result->provider_reference,
                'provider_transaction_id' => $result->provider_transaction_id,
                'payer' => $result->payer,
                'meta' => $result->meta,
            ]);

            $collected = new VoucherPaymentResultData(
                voucher_code: $result->voucher_code,
                status: 'collected',
                amount: $result->amount,
                currency: $result->currency,
                provider: $result->provider,
                provider_reference: $result->provider_reference,
                provider_transaction_id: $result->provider_transaction_id,
                payer: $result->payer,
                wallet: [
                    'transaction_id' => $transaction->getKey(),
                ],
                meta: $result->meta,
                messages: ['Voucher funds collected successfully.'],
            );

            $this->collections->handle(
                voucher: $voucher,
                result: $collected,
                payload: $payload,
                walletTransaction: $transaction,
            );

            $this->progress->persistSummary($voucher);

            return $collected;
        });
    }
}
