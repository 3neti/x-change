<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Contracts\VoucherCollectionPostingContract;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Data\Payment\VoucherCollectionPostingData;
use LBHurtado\XChange\Services\Cockpit\CockpitPosSaleReferenceService;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class ProviderWalletCollectionPosting implements VoucherCollectionPostingContract
{
    public function __construct(
        private VoucherCollectionWalletResolverContract $wallets,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryInventoryOperationContract $treasury,
        private TreasuryInventoryRegistrationService $inventoryRegistration,
        private VerifiedTreasuryFundingAllocationContract $allocations,
        private CockpitPosSaleReferenceService $posSaleReferences,
    ) {}

    public function driver(): string
    {
        return 'provider_wallet';
    }

    public function post(
        Voucher $voucher,
        ConfirmedVoucherCollectionData $collection,
    ): VoucherCollectionPostingData {
        $provider = mb_strtolower(trim((string) $collection->provider));
        $currency = mb_strtoupper(trim($collection->currency));
        $connections = collect($this->connections->active())
            ->filter(
                static fn ($connection): bool => $connection->provider === $provider
                    && $connection->currency === $currency,
            )
            ->values();

        if ($connections->count() === 0) {
            return $this->postToCompatibilityWallet($voucher, $collection);
        }

        if ($connections->count() !== 1) {
            throw new RuntimeException(
                'Voucher collection requires exactly one active Treasury connection.',
            );
        }

        $connection = $connections->sole();
        $wallet = $this->wallets->resolve($voucher);
        $walletUuid = data_get($wallet, 'uuid');
        $accountReference = is_string($walletUuid) && trim($walletUuid) !== ''
            ? 'wallet:'.trim($walletUuid)
            : 'wallet:'.$wallet->getKey();
        $providerTransactionId = trim((string) (
            $collection->providerTransactionId
            ?? $collection->providerReference
            ?? $collection->authorityReference
        ));

        if ($providerTransactionId === '') {
            throw new RuntimeException(
                'Treasury-backed voucher collection requires a provider transaction reference.',
            );
        }

        $scope = hash('sha256', implode('|', [
            $provider,
            $providerTransactionId,
            $currency,
            (string) $collection->amountMinor,
        ]));
        $inventoryOperationReference = 'voucher-collection-recognition:'.$scope;
        $saleReference = $this->posSaleReferences->saleReferenceForVoucherId($voucher->getKey());

        $this->inventoryRegistration->ensure(new TreasuryInventoryData(
            inventoryReference: $connection->inventoryReference,
            resourceType: $connection->settlementResourceType,
            currency: $currency,
            capacityMinor: 0,
            status: 'requested',
            idempotencyKey: 'register:'.$connection->inventoryReference,
            externalReference: $connection->settlementResourceReference,
            metadata: ['provider' => $provider],
        ));

        $recognition = $this->treasury->recognize(
            new TreasuryInventoryRecognitionData(
                operationReference: $inventoryOperationReference,
                inventoryReference: $connection->inventoryReference,
                settlementResourceReference: $connection->settlementResourceReference,
                amountMinor: $collection->amountMinor,
                currency: $currency,
                status: 'requested',
                idempotencyKey: 'voucher-collection-recognition-key:'.$scope,
                effectiveAt: now()->toRfc3339String(),
                externalReference: $provider.':'.$providerTransactionId,
                metadata: [
                    'source' => 'verified_voucher_collection',
                    'voucher_code' => (string) $voucher->code,
                    'provider' => $provider,
                    'provider_transaction_id' => $providerTransactionId,
                    'sale_reference' => $saleReference,
                ],
            ),
        );
        $allocation = $this->allocations->allocate(
            accountReference: $accountReference,
            provider: $provider,
            amountMinor: $collection->amountMinor,
            currency: $currency,
            evidenceReference: 'voucher-collection:'.$provider.':'.$providerTransactionId,
            metadata: [
                'source' => 'verified_voucher_collection',
                'voucher_code' => (string) $voucher->code,
                'provider' => $provider,
                'provider_transaction_id' => $providerTransactionId,
                'inventory_operation_reference' => $recognition->operationReference,
                'sale_reference' => $saleReference,
            ],
        );

        return new VoucherCollectionPostingData(
            treasuryOperationReference: $recognition->operationReference,
            metadata: [
                'provider' => $provider,
                'provider_reference' => $collection->providerReference,
                'provider_transaction_id' => $collection->providerTransactionId,
                'provider_calls' => true,
                'provider_inventory_changed' => true,
                'treasury_position_recognition_reference' => $allocation->recognitionOperationReference,
                'treasury_position_allocation_reference' => $allocation->allocationOperationReference,
                'treasury_position_transaction_id' => $allocation->destinationTransactionId,
                'treasury_position_transaction_uuid' => $allocation->destinationTransactionUuid,
                'treasury_position_transfer_uuid' => $allocation->transferUuid,
                'sale_reference' => $saleReference,
            ],
        );
    }

    private function postToCompatibilityWallet(
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
