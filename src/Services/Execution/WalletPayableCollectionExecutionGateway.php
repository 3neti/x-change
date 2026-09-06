<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Contracts\PayableCollectionExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\PayableCollectionRejectedException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;

final readonly class WalletPayableCollectionExecutionGateway implements PayableCollectionExecutionGateway
{
    public function __construct(
        private VoucherCollectionWalletResolverContract $wallets,
        private CollectVoucherFunds $collect,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function authorize(ExecutionContextData $context, string $executionId): array
    {
        $voucher = $this->voucher($context);

        try {
            $this->wallets->resolve($voucher);
        } catch (PayCodeWalletNotResolved $exception) {
            throw new PayableCollectionRejectedException(
                'The payable collection wallet is not authorized.',
                previous: $exception,
            );
        }

        return ['collection_wallet_authorized' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function credit(
        ExecutionContextData $context,
        int $amountMinor,
        string $providerTransactionId,
        string $executionId,
    ): array {
        $voucher = $this->voucher($context);
        $amount = $amountMinor / 100;
        $provider = trim((string) ($context->meta['provider'] ?? ''));
        $providerReference = trim((string) ($context->meta['provider_reference'] ?? ''));
        $idempotencyKey = trim((string) ($context->meta['idempotency_key'] ?? ''));

        $result = $this->collect->collectConfirmed(
            $voucher,
            new VoucherPaymentResultData(
                voucher_code: (string) $voucher->code,
                status: 'succeeded',
                amount: $amount,
                currency: (string) ($context->meta['currency'] ?? 'PHP'),
                provider: $provider,
                provider_reference: $providerReference,
                provider_transaction_id: $providerTransactionId,
                meta: [
                    'payment_attempt_reference' => $providerReference,
                    'provider_observation_id' => $context->meta['provider_observation_id'] ?? null,
                    'verification_source' => $context->meta['verification_source'] ?? null,
                ],
                messages: ['Pay Code payment collected successfully.'],
            ),
            [
                'amount' => $amount,
                'currency' => (string) ($context->meta['currency'] ?? 'PHP'),
                'status' => 'succeeded',
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'provider_transaction_id' => $providerTransactionId,
                'idempotency_key' => $idempotencyKey,
            ],
        );

        $collectionId = (int) ($result->meta['collection_id'] ?? 0);

        if ($collectionId <= 0) {
            throw new PayableCollectionRejectedException(
                'Payable collection did not produce durable collection evidence.',
            );
        }

        return [
            'voucher_collection_id' => $collectionId,
            'collection_status' => $result->status,
        ];
    }

    private function voucher(ExecutionContextData $context): Voucher
    {
        if (! $context->voucher instanceof Voucher || ! $context->voucher->exists) {
            throw new PayableCollectionRejectedException(
                'Payable collection execution requires a persisted Pay Code.',
            );
        }

        return $context->voucher;
    }
}
