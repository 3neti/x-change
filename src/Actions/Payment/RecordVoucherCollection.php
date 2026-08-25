<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Bavix\Wallet\Models\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Exceptions\VoucherCollectionConflict;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Payment\VoucherCollectionJournal;
use LBHurtado\XChange\Services\VoucherCollectionIdempotencyService;

final readonly class RecordVoucherCollection
{
    public function __construct(
        private VoucherCollectionIdempotencyService $idempotency,
        private VoucherCollectionJournal $journal,
    ) {}

    public function handle(
        Voucher $voucher,
        VoucherPaymentResultData $result,
        array $payload = [],
        ?Transaction $walletTransaction = null,
    ): VoucherCollection {
        $payload = $this->normalisePayload($result, $payload);

        return DB::transaction(function () use (
            $voucher,
            $result,
            $payload,
            $walletTransaction,
        ): VoucherCollection {
            $locked = Voucher::query()
                ->lockForUpdate()
                ->findOrFail($voucher->getKey());
            $existing = $this->idempotency->findExisting($locked, $payload);

            if ($existing instanceof VoucherCollection) {
                if ((int) $existing->voucher_id !== (int) $locked->getKey()) {
                    throw VoucherCollectionConflict::forProviderTransactionId(
                        (string) $payload['provider'],
                        (string) $payload['provider_transaction_id'],
                    );
                }

                if (! $this->idempotency->payloadMatches($existing, $payload)) {
                    $key = (string) Arr::get($payload, 'idempotency_key');

                    if ($key !== '') {
                        throw VoucherCollectionConflict::forIdempotencyKey($key);
                    }

                    throw VoucherCollectionConflict::forProviderReference(
                        (string) Arr::get($payload, 'provider'),
                        (string) Arr::get($payload, 'provider_reference'),
                    );
                }

                return $existing;
            }

            $collectionNumber = ((int) VoucherCollection::query()
                ->where('voucher_id', $voucher->getKey())
                ->max('collection_number')) + 1;

            $requestedAmount = (float) Arr::get($payload, 'amount', $result->amount);
            $collectedAmount = $result->succeeded() || $result->status === 'collected'
                ? $result->amount
                : 0.0;
            $collection = VoucherCollection::query()->create([
                'voucher_id' => $locked->getKey(),
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
                'idempotency_fingerprint' => $this->fingerprint($payload),

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

            DB::afterCommit(
                fn () => $this->journal->record($collection->fresh()),
            );

            return $collection;
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalisePayload(
        VoucherPaymentResultData $result,
        array $payload,
    ): array {
        return array_replace([
            'amount' => $result->amount,
            'currency' => $result->currency,
            'status' => $result->status,
            'provider' => $result->provider,
            'provider_reference' => $result->provider_reference,
            'provider_transaction_id' => $result->provider_transaction_id,
        ], $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode([
            'amount' => round((float) Arr::get($payload, 'amount', 0), 2),
            'currency' => mb_strtoupper((string) Arr::get($payload, 'currency', 'PHP')),
            'status' => Arr::get($payload, 'status'),
            'provider' => Arr::get($payload, 'provider'),
            'provider_reference' => Arr::get($payload, 'provider_reference'),
            'provider_transaction_id' => Arr::get($payload, 'provider_transaction_id'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
