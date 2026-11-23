<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Contracts\VoucherCollectionPostingContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Data\Payment\VoucherCollectionPostingData;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Banking\AssignedFundingTreasuryConnection;
use LBHurtado\XChange\Services\Cockpit\CockpitPosSaleReferenceService;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialSaleService;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;
use LBHurtado\XChange\Services\Execution\VoucherCollectionWalletSelector;
use LBHurtado\XChange\Services\Execution\VoucherValueFlowPolicy;
use LBHurtado\XChange\Services\Funding\SharedFundingTreasuryConnection;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class ProviderWalletCollectionPosting implements VoucherCollectionPostingContract
{
    public function __construct(
        private VoucherCollectionWalletSelector $wallets,
        private VoucherValueFlowPolicy $valueFlows,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryInventoryOperationContract $treasury,
        private TreasuryInventoryRegistrationService $inventoryRegistration,
        private VerifiedTreasuryFundingAllocationContract $allocations,
        private CockpitPosSaleReferenceService $posSaleReferences,
        private ValueFlowPayoutService $payouts,
    ) {}

    public function driver(): string
    {
        return 'provider_wallet';
    }

    public function post(
        Voucher $voucher,
        ConfirmedVoucherCollectionData $collection,
    ): VoucherCollectionPostingData {
        return DB::transaction(fn () => $this->postAtomically($voucher, $collection), attempts: 5);
    }

    private function postAtomically(Voucher $voucher, ConfirmedVoucherCollectionData $collection): VoucherCollectionPostingData
    {
        if ($this->valueFlows->source($voucher) !== null) {
            throw new RuntimeException(
                'Explicit collection sources require provider-verified payer account evidence.',
            );
        }

        $commercial = app(PayCodeCommercialSaleService::class);
        $feeMinor = $commercial->outstandingCollectionFee($voucher);
        $principalMinor = $collection->amountMinor - $feeMinor;
        if ($principalMinor <= 0) {
            throw new RuntimeException('Collection must cover the fee and a positive principal.');
        }
        $provider = mb_strtolower(trim((string) $collection->provider));
        $currency = mb_strtoupper(trim($collection->currency));
        $splitId = data_get($collection->metadata, 'reviewed_split_id');
        $split = null;
        if ($splitId !== null) {
            $split = DB::table('x_change_reviewed_payment_splits')->where('id', $splitId)->lockForUpdate()->first();
            $transactionId = trim((string) $collection->providerTransactionId);
            if ($split === null || (int) $split->voucher_id !== (int) $voucher->getKey()
                || $split->provider_code !== $provider || $split->currency !== $currency
                || ! hash_equals($split->provider_transaction_hash, hash('sha256', $transactionId))
                || (int) $split->provider_observation_id !== (int) data_get($collection->metadata, 'provider_observation_id')
                || (int) $split->collection_amount_minor !== $collection->amountMinor
                || (int) $split->excess_amount_minor <= 0
                || (int) $split->gross_amount_minor !== $collection->amountMinor + (int) $split->excess_amount_minor) {
                throw new RuntimeException('The reviewed split does not match the collection evidence.');
            }
        }
        $connections = collect($this->connections->active())
            ->filter(
                static fn ($connection): bool => $connection->provider === $provider
                    && $connection->currency === $currency,
            )
            ->values();

        if ($connections->count() === 0 && ($split !== null || $this->payouts->isExternal($voucher) || data_get($voucher->metadata, 'instructions.fee_bearer', 'issuer') === 'payer')) {
            throw new RuntimeException('An external destination requires a Treasury-backed collection.');
        }

        if ($connections->count() === 0) {
            return $this->postToCompatibilityWallet($voucher, $collection);
        }

        $fundingSnapshot = null;
        $attempt = PaymentAttempt::query()
            ->where('reference', $collection->providerReference)
            ->where('voucher_id', $voucher->getKey())
            ->where('provider_code', $provider)
            ->where('provider_transaction_id', $collection->providerTransactionId)
            ->where('matched_observation_id', data_get($collection->metadata, 'provider_observation_id'))
            ->first();
        if ($attempt === null && is_string($collection->providerTransactionId)
            && $collection->providerReference === 'provider-payment:'.hash('sha256', implode('|', [$provider, $collection->providerTransactionId]))) {
            $observed = ObservedPaymentTransaction::query()->with('paymentAttempt')
                ->where('provider_code', $provider)
                ->where('provider_transaction_hash', hash('sha256', $collection->providerTransactionId))->first();
            $candidate = $observed?->paymentAttempt;
            $observation = ProviderFundingObservation::query()->find(data_get($collection->metadata, 'provider_observation_id'));
            if ($candidate !== null && $observed !== null && $observation !== null
                && (int) $candidate->voucher_id === (int) $voucher->getKey()
                && app(SpecificPaymentAttribution::class)->matches($candidate, $observed, $observation)) {
                $attempt = $candidate;
            }
        }
        $snapshot = $attempt?->destination_snapshot_ciphertext;
        if (is_array($snapshot) && data_get($snapshot, 'mode') === 'dedicated') {
            if (! $voucher->owner instanceof Model) {
                throw new RuntimeException('The assigned account owner is unavailable.');
            }
            $connection = app(AssignedFundingTreasuryConnection::class)->resolve($voucher->owner, $provider, $currency, $snapshot);
            $fundingSnapshot = $snapshot;
        } elseif ($connections->count() !== 1) {
            if (! is_array($snapshot) || data_get($snapshot, 'mode') !== 'shared') {
                throw new RuntimeException('Voucher collection requires an unambiguous shared Treasury connection.');
            }
            $connection = app(SharedFundingTreasuryConnection::class)->resolve($provider, $currency, $snapshot);
            $fundingSnapshot = $snapshot;
        } else {
            $connection = $connections->sole();
        }
        $wallet = $this->wallets->resolve($voucher, $provider, $currency, $connection->reference);
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
            (string) ($split?->gross_amount_minor ?? $collection->amountMinor),
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
                amountMinor: (int) ($split?->gross_amount_minor ?? $collection->amountMinor),
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
            amountMinor: $principalMinor,
            currency: $currency,
            evidenceReference: 'voucher-collection:'.$provider.':'.$providerTransactionId,
            metadata: [
                'source' => 'verified_voucher_collection',
                'voucher_code' => (string) $voucher->code,
                'provider' => $provider,
                'provider_transaction_id' => $providerTransactionId,
                'provider_observation_id' => data_get($collection->metadata, 'provider_observation_id'),
                'payment_attempt_reference' => $attempt?->reference,
                'inventory_operation_reference' => $recognition->operationReference,
                'sale_reference' => $saleReference,
                ...($fundingSnapshot === null ? [] : ['funding_destination_snapshot' => $fundingSnapshot]),
            ],
        );

        $excessAllocation = null;
        if ($split !== null) {
            $issuerWallet = app(WalletAccessContract::class)->resolveForUser($voucher->owner);
            $excessAllocation = $this->allocations->allocate(
                accountReference: 'wallet:'.$issuerWallet->uuid,
                provider: $provider,
                amountMinor: (int) $split->excess_amount_minor,
                currency: $currency,
                evidenceReference: 'reviewed-split-excess:'.$provider.':'.$providerTransactionId,
                metadata: ['source' => 'reviewed_split_excess', 'reviewed_split_id' => $splitId,
                    'provider_transaction_id' => $providerTransactionId, 'inventory_operation_reference' => $recognition->operationReference,
                    ...($fundingSnapshot === null ? [] : ['funding_destination_snapshot' => $fundingSnapshot])],
            );
        }

        if ($feeMinor > 0) {
            $issuerWallet = app(WalletAccessContract::class)->resolveForUser($voucher->owner);
            $this->allocations->allocate(
                accountReference: 'wallet:'.$issuerWallet->uuid,
                provider: $provider,
                amountMinor: $feeMinor,
                currency: $currency,
                evidenceReference: 'voucher-collection-fee:'.$provider.':'.$providerTransactionId,
                metadata: [
                    'source' => 'verified_voucher_collection_fee',
                    'voucher_code' => (string) $voucher->code,
                    'inventory_operation_reference' => $recognition->operationReference,
                    ...($fundingSnapshot === null ? [] : ['funding_destination_snapshot' => $fundingSnapshot]),
                ],
            );
        }
        if (data_get($voucher->metadata, 'instructions.fee_bearer', 'issuer') === 'payer') {
            $commercial->collectFee($voucher);
        }

        $payout = $this->payouts->isExternal($voucher)
            ? $this->payouts->prepare(
                $voucher,
                $this->payouts->bridge($voucher, $connection->reference, $currency),
                $principalMinor,
                'voucher-collection:'.$provider.':'.$providerTransactionId,
            ) : null;

        return new VoucherCollectionPostingData(
            treasuryOperationReference: $recognition->operationReference,
            metadata: [
                'provider' => $provider,
                'provider_reference' => $collection->providerReference,
                'provider_transaction_id' => $collection->providerTransactionId,
                'provider_calls' => true,
                'provider_inventory_changed' => true,
                'principal_amount_minor' => $principalMinor,
                'fee_amount_minor' => $feeMinor,
                'provider_gross_amount_minor' => (int) ($split?->gross_amount_minor ?? $collection->amountMinor),
                'reviewed_split_id' => $splitId,
                'excess_amount_minor' => (int) ($split?->excess_amount_minor ?? 0),
                'excess_allocation_reference' => $excessAllocation?->allocationOperationReference,
                'payout_reference' => $payout?->provider_reference,
                'payout_status' => $payout?->status,
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
        $transaction = $this->wallets->resolve(
            $voucher,
            (string) $collection->provider,
            $collection->currency,
        )->deposit(
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
