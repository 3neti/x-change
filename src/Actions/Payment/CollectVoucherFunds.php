<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Contracts\VoucherPaymentConfirmationContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Exceptions\VoucherCannotCollect;

class CollectVoucherFunds
{
    public function __construct(
        protected VoucherFlowCapabilityResolverContract $capabilities,
        protected VoucherPaymentConfirmationContract $confirmation,
    ) {}

    public function handle(Voucher $voucher, Wallet $wallet, array $payload): VoucherPaymentResultData
    {
        $capabilities = $this->capabilities->resolve($voucher);

        if (! $capabilities->can_collect) {
            throw VoucherCannotCollect::forVoucher($voucher, $capabilities);
        }

        return DB::transaction(function () use ($voucher, $wallet, $payload): VoucherPaymentResultData {
            $result = $this->confirmation->confirm($voucher, $payload);

            if (! $result->succeeded()) {
                return $result;
            }

            $transaction = $wallet->depositFloat($result->amount, [
                'reason' => 'voucher_collection',
                'voucher_code' => $result->voucher_code,
                'provider' => $result->provider,
                'provider_reference' => $result->provider_reference,
                'provider_transaction_id' => $result->provider_transaction_id,
                'idempotency_key' => $payload['idempotency_key'] ?? (string) Str::uuid(),
                'payer' => $result->payer,
                'meta' => $result->meta,
            ]);

            return new VoucherPaymentResultData(
                voucher_code: $result->voucher_code,
                status: 'collected',
                amount: $result->amount,
                currency: $result->currency,
                provider: $result->provider,
                provider_reference: $result->provider_reference,
                provider_transaction_id: $result->provider_transaction_id,
                payer: $result->payer,
                wallet: [
                    'transaction_id' => $transaction->id ?? null,
                    'balance' => method_exists($wallet, 'balanceFloat')
                        ? $wallet->balanceFloat
                        : null,
                ],
                meta: $result->meta,
                messages: ['Voucher funds collected successfully.'],
            );
        });
    }
}
