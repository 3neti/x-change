<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherPaymentConfirmationContract;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Services\SettlementCollectionGate;
use LBHurtado\XChange\Services\VoucherCapabilityGuard;
use LBHurtado\XChange\Services\VoucherCollectionIdempotencyService;

class CollectVoucherFunds
{
    public function __construct(
        protected VoucherCapabilityGuard $guard,
        protected VoucherPaymentConfirmationContract $confirmation,
        protected RecordVoucherCollection $collections,
        protected VoucherCollectionIdempotencyService $idempotency,
        protected SettlementCollectionGate $settlementCollectionGate,
        protected CompleteVoucherCollection $complete,
    ) {}

    public function handle(Voucher $voucher, array $payload): VoucherPaymentResultData
    {
        $this->guard->ensureCanCollect($voucher);

        if ($replay = $this->idempotency->findReplay($voucher, $payload)) {
            return $replay;
        }

        $this->settlementCollectionGate->ensureCollectibleSettlementIsReady(
            voucher: $voucher,
            context: $this->settlementCollectionGate->contextFromVoucher($voucher),
        );

        $result = $this->confirmation->confirm($voucher, $payload);

        if (! $result->succeeded()) {
            $this->collections->handle(
                voucher: $voucher,
                result: $result,
                payload: $payload,
            );

            return $result;
        }

        return $this->collectConfirmed($voucher, $result, $payload);
    }

    /**
     * Accepts only a server-built result from an authoritative provider
     * verification pipeline. Browser payloads must use handle().
     *
     * @param  array<string, mixed>  $payload
     */
    public function collectConfirmed(
        Voucher $voucher,
        VoucherPaymentResultData $result,
        array $payload,
    ): VoucherPaymentResultData {
        $this->guard->ensureCanCollect($voucher);

        if ($replay = $this->idempotency->findReplay($voucher, $payload)) {
            return $replay;
        }

        $this->settlementCollectionGate->ensureCollectibleSettlementIsReady(
            voucher: $voucher,
            context: $this->settlementCollectionGate->contextFromVoucher($voucher),
        );

        if (! $result->succeeded()) {
            throw new \InvalidArgumentException('Authoritative collection result must be succeeded.');
        }

        $collection = $this->complete->handle(
            $voucher,
            new ConfirmedVoucherCollectionData(
                amountMinor: (int) round($result->amount * 100),
                currency: $result->currency,
                executionDriver: 'provider_wallet',
                authority: 'authoritative_provider',
                authorityReference: (string) (
                    $result->provider_transaction_id
                    ?? $result->provider_reference
                ),
                idempotencyKey: trim((string) Arr::get(
                    $payload,
                    'idempotency_key',
                    implode(':', [
                        'provider-collection',
                        (string) $result->provider,
                        (string) $result->provider_reference,
                    ]),
                )),
                provider: $result->provider,
                providerReference: $result->provider_reference,
                providerTransactionId: $result->provider_transaction_id,
                payerName: Arr::get($result->payer, 'name'),
                payerMobile: Arr::get($result->payer, 'mobile'),
                metadata: [
                    ...$result->meta,
                    'confirmed_payload' => $payload,
                ],
            ),
        );

        return new VoucherPaymentResultData(
            voucher_code: $result->voucher_code,
            status: 'collected',
            amount: $collection->collectedAmount(),
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
                ...$result->meta,
                'collection_id' => $collection->getKey(),
            ],
            messages: ['Voucher funds collected successfully.'],
        );
    }
}
