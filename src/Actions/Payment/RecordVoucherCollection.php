<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Bavix\Wallet\Models\Transaction;
use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Models\VoucherCollection;

class RecordVoucherCollection
{
    public function handle(
        Voucher $voucher,
        VoucherPaymentResultData $result,
        array $payload = [],
        ?Transaction $walletTransaction = null,
    ): VoucherCollection {
        $collectionNumber = ((int) VoucherCollection::query()
                ->where('voucher_id', $voucher->getKey())
                ->max('collection_number')) + 1;

        $requestedAmount = (float) Arr::get($payload, 'amount', $result->amount);
        $collectedAmount = $result->succeeded() || $result->status === 'collected'
            ? $result->amount
            : 0.0;

        return VoucherCollection::query()->create([
            'voucher_id' => $voucher->getKey(),
            'collection_number' => $collectionNumber,

            'status' => $result->status,

            'requested_amount_minor' => (int) round($requestedAmount * 100),
            'collected_amount_minor' => (int) round($collectedAmount * 100),
            'currency' => $result->currency,

            'provider' => $result->provider,
            'provider_reference' => $result->provider_reference,
            'provider_transaction_id' => $result->provider_transaction_id,

            'payer_mobile' => Arr::get($result->payer, 'mobile'),
            'payer_name' => Arr::get($result->payer, 'name'),

            'wallet_transaction_id' => $walletTransaction?->getKey(),
            'idempotency_key' => Arr::get($payload, 'idempotency_key'),

            'attempted_at' => now(),
            'completed_at' => $result->succeeded() || $result->status === 'collected'
                ? now()
                : null,

            'failure_message' => $result->succeeded() || $result->status === 'collected'
                ? null
                : Arr::first($result->messages),

            'meta' => [
                'payload' => $payload,
                'result' => $result->toArray(),
            ],
        ]);
    }
}
