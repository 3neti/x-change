<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Exceptions\VoucherCollectionConflict;
use LBHurtado\XChange\Models\VoucherCollection;

class VoucherCollectionIdempotencyService
{
    public function findReplay(Voucher $voucher, array $payload): ?VoucherPaymentResultData
    {
        $existing = $this->findExisting($voucher, $payload);

        if (! $existing) {
            return null;
        }

        if (! $this->payloadMatches($existing, $payload)) {
            $key = (string) Arr::get($payload, 'idempotency_key');

            if ($key !== '') {
                throw VoucherCollectionConflict::forIdempotencyKey($key);
            }

            throw VoucherCollectionConflict::forProviderReference(
                (string) Arr::get($payload, 'provider'),
                (string) Arr::get($payload, 'provider_reference'),
            );
        }

        return $this->toResult($existing);
    }

    public function findExisting(Voucher $voucher, array $payload): ?VoucherCollection
    {
        $idempotencyKey = trim((string) Arr::get($payload, 'idempotency_key', ''));

        if ($idempotencyKey !== '') {
            return VoucherCollection::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->latest('id')
                ->first();
        }

        $provider = trim((string) Arr::get($payload, 'provider', ''));
        $providerReference = trim((string) Arr::get($payload, 'provider_reference', ''));

        if ($provider !== '' && $providerReference !== '') {
            return VoucherCollection::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('provider', $provider)
                ->where('provider_reference', $providerReference)
                ->latest('id')
                ->first();
        }

        return null;
    }

    protected function payloadMatches(VoucherCollection $collection, array $payload): bool
    {
        $stored = (array) data_get($collection->meta, 'payload', []);

        return round((float) Arr::get($stored, 'amount', 0), 2) === round((float) Arr::get($payload, 'amount', 0), 2)
            && strtoupper((string) Arr::get($stored, 'currency', 'PHP')) === strtoupper((string) Arr::get($payload, 'currency', 'PHP'))
            && (string) Arr::get($stored, 'provider', '') === (string) Arr::get($payload, 'provider', '')
            && (string) Arr::get($stored, 'provider_reference', '') === (string) Arr::get($payload, 'provider_reference', '')
            && (string) Arr::get($stored, 'provider_transaction_id', '') === (string) Arr::get($payload, 'provider_transaction_id', '')
            && (string) Arr::get($stored, 'status', 'succeeded') === (string) Arr::get($payload, 'status', 'succeeded');
    }

    protected function toResult(VoucherCollection $collection): VoucherPaymentResultData
    {
        return new VoucherPaymentResultData(
            voucher_code: $collection->voucher->code,
            status: $collection->status,
            amount: $collection->collectedAmount() > 0
                ? $collection->collectedAmount()
                : $collection->requestedAmount(),
            currency: $collection->currency,
            provider: $collection->provider,
            provider_reference: $collection->provider_reference,
            provider_transaction_id: $collection->provider_transaction_id,
            payer: [
                'name' => $collection->payer_name,
                'mobile' => $collection->payer_mobile,
            ],
            wallet: [
                'transaction_id' => $collection->wallet_transaction_id,
            ],
            meta: [
                'replayed' => true,
                'collection_id' => $collection->getKey(),
            ],
            messages: ['Voucher collection replayed from previous confirmation.'],
        );
    }
}
