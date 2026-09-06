<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Bavix\Wallet\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Data\Treasury\VoucherCollectionTreasuryCorrectionData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

final readonly class VoucherCollectionTreasuryCorrectionService
{
    public function __construct(
        private VoucherCollectionWalletResolverContract $wallets,
        private TreasuryProviderConnectionCatalog $connections,
        private AccountBalanceReadModelContract $balances,
        private TreasuryInventoryOperationContract $inventoryOperations,
        private TreasuryPositionOperationContract $positionOperations,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private VoucherCollectionTreasuryCorrectionJournal $journal,
    ) {}

    public function inspect(string $code): VoucherCollectionTreasuryCorrectionData
    {
        return $this->plan($code);
    }

    public function correct(string $code): VoucherCollectionTreasuryCorrectionData
    {
        $normalizedCode = mb_strtoupper(trim($code));

        return Cache::lock(
            'x-change:voucher-collection-treasury-correction:'.hash('sha256', $normalizedCode),
            60,
        )->block(5, function () use ($normalizedCode): VoucherCollectionTreasuryCorrectionData {
            $plan = $this->inspect($normalizedCode);

            if ($plan->status === 'already_corrected') {
                return $plan;
            }

            if ($plan->status !== 'ready') {
                throw new TreasuryConfigurationException(
                    'The voucher collection Treasury correction is not eligible for commit.',
                );
            }

            return DB::transaction(function () use ($normalizedCode, $plan): VoucherCollectionTreasuryCorrectionData {
                $voucher = Voucher::query()->where('code', $normalizedCode)->lockForUpdate()->sole();
                $collection = VoucherCollection::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->whereKey($plan->collectionId)
                    ->lockForUpdate()
                    ->sole();
                $this->assertLockedEvidence($voucher, $collection, $plan);
                $connection = $this->connection($plan->provider, $plan->currency);
                $references = $plan->operationReferences;
                $wallet = $this->wallets->resolve($voucher);
                $owner = $wallet->holder;

                if (! $owner instanceof Model) {
                    throw new TreasuryConfigurationException(
                        'The collection wallet owner could not be resolved for correction.',
                    );
                }

                $source = $this->systemPosition(
                    $connection,
                    TreasuryPositionPurpose::TreasuryClearing,
                );
                $destination = $this->clientFundsPosition($owner, $connection);
                $metadata = [
                    'source' => 'voucher_collection_treasury_correction',
                    'voucher_code' => $normalizedCode,
                    'voucher_collection_id' => $collection->getKey(),
                    'original_wallet_transaction_id' => $collection->wallet_transaction_id,
                    'provider_calls' => false,
                ];

                $this->inventoryOperations->recognize(new TreasuryInventoryRecognitionData(
                    operationReference: $references['inventory_recognition'],
                    inventoryReference: $connection->inventoryReference,
                    settlementResourceReference: $connection->settlementResourceReference,
                    amountMinor: $plan->amountMinor,
                    currency: $plan->currency,
                    status: 'requested',
                    idempotencyKey: $references['inventory_recognition'].':key',
                    effectiveAt: $collection->completed_at?->toRfc3339String(),
                    externalReference: $plan->provider.':'.$collection->provider_transaction_id,
                    metadata: $metadata,
                ));
                $recognition = $this->positionOperations->recognize(
                    new TreasuryPositionRecognitionData(
                        operationReference: $references['position_recognition'],
                        destinationPositionReference: $source->positionReference,
                        amountMinor: $plan->amountMinor,
                        currency: $plan->currency,
                        idempotencyKey: $references['position_recognition'].':key',
                        externalReference: $references['evidence'],
                        metadata: $metadata,
                    ),
                );
                $allocation = $this->positionOperations->allocate(
                    new TreasuryPositionAllocationData(
                        operationReference: $references['position_allocation'],
                        sourcePositionReference: $source->positionReference,
                        destinationPositionReference: $destination->positionReference,
                        amountMinor: $plan->amountMinor,
                        currency: $plan->currency,
                        idempotencyKey: $references['position_allocation'].':key',
                        externalReference: $recognition->operationReference,
                        metadata: $metadata,
                    ),
                );

                if (
                    ! $wallet->refreshBalance()
                    || $wallet->refresh()->getBalanceIntAttribute()
                        !== $plan->compatibilityBalanceMinor
                ) {
                    throw new TreasuryConfigurationException(
                        'The compatibility wallet projection could not be preserved exactly.',
                    );
                }
                $committed = new VoucherCollectionTreasuryCorrectionData(
                    status: 'corrected',
                    voucherCode: $normalizedCode,
                    collectionId: $plan->collectionId,
                    provider: $plan->provider,
                    currency: $plan->currency,
                    amountMinor: $plan->amountMinor,
                    compatibilityBalanceMinor: $plan->compatibilityBalanceMinor,
                    clientFundsBalanceMinor: $plan->clientFundsBalanceMinor + $plan->amountMinor,
                    divergenceMinor: 0,
                    walletTransactionId: $plan->walletTransactionId,
                    operationReferences: [
                        ...$references,
                        'position_recognition' => $recognition->operationReference,
                        'position_allocation' => $allocation->operationReference,
                    ],
                    committed: true,
                );
                $this->journal->record($committed);

                return $committed;
            }, attempts: 5);
        });
    }

    private function assertLockedEvidence(
        Voucher $voucher,
        VoucherCollection $collection,
        VoucherCollectionTreasuryCorrectionData $plan,
    ): void {
        $attempt = PaymentAttempt::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('voucher_collection_id', $collection->getKey())
            ->lockForUpdate()
            ->sole();
        $observation = ProviderFundingObservation::query()
            ->whereKey($attempt->matched_observation_id)
            ->lockForUpdate()
            ->first();
        $wallet = $this->wallets->resolve($voucher);
        $transaction = Transaction::query()
            ->whereKey($collection->wallet_transaction_id)
            ->lockForUpdate()
            ->first();

        if (
            $collection->execution_driver !== 'provider_wallet'
            || ! in_array($collection->status, ['collected', 'succeeded'], true)
            || $collection->provider !== $plan->provider
            || $collection->currency !== $plan->currency
            || $collection->collected_amount_minor !== $plan->amountMinor
            || $attempt->status !== PaymentAttemptStatus::Settled
            || ! $observation instanceof ProviderFundingObservation
            || $observation->provider_status !== 'settled'
            || $observation->provider_transaction_id !== $collection->provider_transaction_id
            || $observation->net_amount_minor !== $collection->collected_amount_minor
            || $observation->currency !== $collection->currency
            || data_get($observation->metadata, 'destination_verified') !== true
            || ! $transaction instanceof Transaction
            || (int) $transaction->wallet_id !== (int) $wallet->getKey()
            || (int) $transaction->amount !== $collection->collected_amount_minor
            || $transaction->type !== 'deposit'
        ) {
            throw new TreasuryConfigurationException(
                'The voucher collection correction evidence changed before commit.',
            );
        }

        $states = [
            TreasuryInventoryOperation::query()
                ->where('operation_reference', $plan->operationReferences['inventory_recognition'])
                ->exists(),
            TreasuryPositionOperation::query()
                ->where('operation_reference', $plan->operationReferences['position_recognition'])
                ->exists(),
            TreasuryPositionOperation::query()
                ->where('operation_reference', $plan->operationReferences['position_allocation'])
                ->exists(),
        ];

        if (in_array(true, $states, true)) {
            throw new TreasuryConfigurationException(
                'Competing voucher collection Treasury correction operations require review.',
            );
        }
    }

    private function plan(string $code): VoucherCollectionTreasuryCorrectionData
    {
        $code = mb_strtoupper(trim($code));
        $voucher = Voucher::query()->where('code', $code)->sole();
        $collection = VoucherCollection::query()
            ->where('voucher_id', $voucher->getKey())
            ->whereIn('status', ['collected', 'succeeded'])
            ->where('execution_driver', 'provider_wallet')
            ->sole();
        $attempt = PaymentAttempt::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('voucher_collection_id', $collection->getKey())
            ->sole();
        $observation = ProviderFundingObservation::query()
            ->whereKey($attempt->matched_observation_id)
            ->first();

        if (
            $attempt->status !== PaymentAttemptStatus::Settled
            || ! $observation instanceof ProviderFundingObservation
            || $observation->provider_status !== 'settled'
            || $observation->provider_transaction_id !== $collection->provider_transaction_id
            || $observation->net_amount_minor !== $collection->collected_amount_minor
            || $observation->currency !== $collection->currency
            || data_get($observation->metadata, 'destination_verified') !== true
        ) {
            throw new TreasuryConfigurationException(
                'The settled collection does not have exact authoritative provider evidence.',
            );
        }

        $wallet = $this->wallets->resolve($voucher);
        $transaction = Transaction::query()->find($collection->wallet_transaction_id);

        if (
            ! $transaction instanceof Transaction
            || (int) $transaction->wallet_id !== (int) $wallet->getKey()
            || (int) $transaction->amount !== $collection->collected_amount_minor
            || $transaction->type !== 'deposit'
        ) {
            throw new TreasuryConfigurationException(
                'The original collection wallet deposit could not be verified exactly.',
            );
        }

        $provider = mb_strtolower(trim((string) $collection->provider));
        $currency = mb_strtoupper(trim($collection->currency));
        $connection = $this->connection($provider, $currency);
        $inventory = TreasuryInventory::query()
            ->where('inventory_reference', $connection->inventoryReference)
            ->first();

        if (! $inventory instanceof TreasuryInventory) {
            throw new TreasuryConfigurationException(
                'The configured Provider Inventory must exist before correction.',
            );
        }

        $scope = hash('sha256', implode('|', [
            (string) $collection->getKey(),
            $provider,
            (string) $collection->provider_transaction_id,
            (string) $collection->collected_amount_minor,
            $currency,
        ]));
        $evidence = 'voucher-collection-correction:'.$scope;
        $allocationScope = hash('sha256', implode('|', [
            $provider,
            $connection->reference,
            $currency,
            $evidence,
        ]));
        $references = [
            'evidence' => $evidence,
            'inventory_recognition' => 'voucher-collection-correction-recognition:'.$scope,
            'position_recognition' => 'position-recognition:'.$allocationScope,
            'position_allocation' => 'position-allocation:'.$allocationScope,
        ];
        $states = [
            TreasuryInventoryOperation::query()->where('operation_reference', $references['inventory_recognition'])->exists(),
            TreasuryPositionOperation::query()->where('operation_reference', $references['position_recognition'])->exists(),
            TreasuryPositionOperation::query()->where('operation_reference', $references['position_allocation'])->exists(),
        ];

        if (in_array(true, $states, true) && in_array(false, $states, true)) {
            throw new TreasuryConfigurationException(
                'A partial voucher collection Treasury correction requires review.',
            );
        }

        $wallet->refresh();
        $compatibilityBalance = $wallet->getBalanceIntAttribute();
        $owner = $wallet->holder;

        if (! $owner instanceof Model) {
            throw new TreasuryConfigurationException(
                'The collection wallet owner could not be resolved.',
            );
        }

        $clientFundsBalance = $this->balances->providerBalanceMinor(
            $owner->fresh(),
            $provider,
            $currency,
        );

        if ($clientFundsBalance === null) {
            throw new TreasuryConfigurationException(
                'The authoritative Client Funds balance could not be resolved.',
            );
        }

        $divergence = $compatibilityBalance - $clientFundsBalance;
        $alreadyCorrected = ExecutionJournalEntry::query()
            ->where(
                'idempotency_key',
                'x-change:voucher-collection:treasury-corrected:'.$collection->getKey(),
            )
            ->exists();

        if ((! $alreadyCorrected && $divergence !== $collection->collected_amount_minor)
            || ($alreadyCorrected && $divergence !== 0)) {
            throw new TreasuryConfigurationException(
                sprintf(
                    'The collection divergence does not match the guarded correction amount (wallet=%d, client_funds=%d, divergence=%d, amount=%d, corrected=%s).',
                    $compatibilityBalance,
                    $clientFundsBalance,
                    $divergence,
                    $collection->collected_amount_minor,
                    $alreadyCorrected ? 'true' : 'false',
                ),
            );
        }

        return new VoucherCollectionTreasuryCorrectionData(
            status: $alreadyCorrected ? 'already_corrected' : 'ready',
            voucherCode: $code,
            collectionId: (int) $collection->getKey(),
            provider: $provider,
            currency: $currency,
            amountMinor: $collection->collected_amount_minor,
            compatibilityBalanceMinor: $compatibilityBalance,
            clientFundsBalanceMinor: $clientFundsBalance,
            divergenceMinor: $divergence,
            walletTransactionId: $collection->wallet_transaction_id,
            operationReferences: $references,
            committed: false,
        );
    }

    private function clientFundsPosition(
        Model $owner,
        TreasuryProviderConnectionData $connection,
    ): TreasuryPositionData {
        $principalReference = $this->principalReferences->resolve($owner);
        $matches = array_values(array_filter(
            $this->positions->forPrincipal($principalReference),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->provider === $connection->provider
                && $position->connectionReference === $connection->reference
                && $position->currency === $connection->currency
                && $position->purpose === TreasuryPositionPurpose::ClientFunds,
        ));

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                'The correction requires one existing Client Funds Position.',
            );
        }

        return $matches[0];
    }

    private function systemPosition(
        TreasuryProviderConnectionData $connection,
        TreasuryPositionPurpose $purpose,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $this->positions->forConnection(
                $connection->provider,
                $connection->reference,
                $connection->currency,
            ),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->purpose === $purpose
                && $position->principalReference === trim((string) config(
                    'x-change.treasury.principal_reference',
                )),
        ));

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                "The correction requires one existing system {$purpose->value} Position.",
            );
        }

        return $matches[0];
    }

    private function connection(
        string $provider,
        string $currency,
    ): TreasuryProviderConnectionData {
        $matches = collect($this->connections->active())
            ->filter(
                static fn ($connection): bool => $connection->provider === $provider
                    && $connection->currency === $currency,
            )
            ->values();

        if ($matches->count() !== 1) {
            throw new TreasuryConfigurationException(
                'The collection requires exactly one active Treasury connection.',
            );
        }

        return $matches->sole();
    }
}
