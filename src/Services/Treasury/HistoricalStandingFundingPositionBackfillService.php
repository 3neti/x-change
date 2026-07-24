<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Bavix\Wallet\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryInventoryOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\FundingAccountRecoveryContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Data\Treasury\HistoricalStandingFundingBackfillData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\AccountFundingReceipt;

final readonly class HistoricalStandingFundingPositionBackfillService
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryProvisioningService $systemPositions,
        private TreasuryAccountPortfolioProvisioningContract $accountPortfolios,
        private TreasuryPositionOperationContract $operations,
        private FundingAccountCreditContract $accounts,
        private FundingAccountRecoveryContract $recoveries,
    ) {}

    public function inspect(
        Model $accountOwner,
        string $connectionReference,
    ): HistoricalStandingFundingBackfillData {
        $connection = $this->connection($connectionReference);
        $receipts = $this->candidates($accountOwner, $connection);

        foreach ($receipts as $receipt) {
            $this->validateReceipt($receipt, $connection);
        }

        return $this->result('dry_run', $connection, $receipts, 0);
    }

    public function backfill(
        Model $accountOwner,
        string $connectionReference,
    ): HistoricalStandingFundingBackfillData {
        $connection = $this->connection($connectionReference);
        $lock = Cache::lock(
            'x-change:treasury:standing-funding-backfill:'.hash('sha256', implode('|', [
                $accountOwner::class,
                (string) $accountOwner->getKey(),
                $connection->reference,
            ])),
            max(1, (int) config('x-change.treasury.migration_lock_seconds', 60)),
        );

        return $lock->block(
            max(0, (int) config('x-change.treasury.migration_lock_wait_seconds', 5)),
            function () use ($accountOwner, $connection): HistoricalStandingFundingBackfillData {
                $receipts = $this->candidates($accountOwner, $connection);

                if ($receipts->isEmpty()) {
                    return $this->result('no_candidates', $connection, $receipts, 0);
                }

                foreach ($receipts as $receipt) {
                    $this->validateReceipt($receipt, $connection);
                }

                $system = $this->systemPositions->provision([$connection->reference]);
                $portfolio = $this->accountPortfolios->provision(
                    $accountOwner,
                    [$connection->reference],
                );
                $source = $this->position(
                    $system->positions,
                    TreasuryPositionPurpose::LegacyUnattributed,
                );
                $destination = $this->position(
                    $portfolio->positions,
                    TreasuryPositionPurpose::ClientFunds,
                );

                return DB::transaction(function () use (
                    $connection,
                    $destination,
                    $receipts,
                    $source,
                ): HistoricalStandingFundingBackfillData {
                    foreach ($receipts as $receipt) {
                        $locked = AccountFundingReceipt::query()
                            ->with('providerFundingObservation')
                            ->lockForUpdate()
                            ->findOrFail($receipt->getKey());
                        $this->backfillReceipt(
                            $locked,
                            $connection,
                            $source,
                            $destination,
                        );
                    }

                    return $this->result(
                        'backfilled',
                        $connection,
                        $receipts,
                        $receipts->count(),
                    );
                }, attempts: 5);
            },
        );
    }

    /**
     * @return Collection<int, AccountFundingReceipt>
     */
    private function candidates(
        Model $accountOwner,
        TreasuryProviderConnectionData $connection,
    ): Collection {
        return AccountFundingReceipt::query()
            ->with(['standingFundingAddress', 'providerFundingObservation'])
            ->where('status', AccountFundingReceiptStatus::Settled)
            ->where('provider_code', $connection->provider)
            ->where('currency', $connection->currency)
            ->whereHas(
                'standingFundingAddress',
                fn ($query) => $query
                    ->where('owner_type', $accountOwner->getMorphClass())
                    ->where('owner_id', $accountOwner->getKey()),
            )
            ->orderBy('id')
            ->get()
            ->reject(
                static fn (AccountFundingReceipt $receipt): bool => filled(
                    data_get(
                        $receipt->metadata,
                        'treasury_position_allocation_reference',
                    ),
                ),
            )
            ->values();
    }

    private function backfillReceipt(
        AccountFundingReceipt $receipt,
        TreasuryProviderConnectionData $connection,
        TreasuryPositionData $source,
        TreasuryPositionData $destination,
    ): void {
        [$account, $legacyCredit] = $this->validateReceipt($receipt, $connection);
        $scope = hash('sha256', implode('|', [
            $connection->provider,
            $connection->reference,
            $receipt->reference,
            $receipt->treasury_operation_reference,
        ]));
        $recognition = $this->operations->recognize(
            new TreasuryPositionRecognitionData(
                operationReference: 'historical-position-recognition:'.$scope,
                destinationPositionReference: $source->positionReference,
                amountMinor: $receipt->net_amount_minor,
                currency: $receipt->currency,
                idempotencyKey: 'historical-position-recognition-key:'.$scope,
                externalReference: (string) $receipt->treasury_operation_reference,
                metadata: [
                    'source' => 'historical_standing_funding_backfill',
                    'account_funding_receipt_reference' => $receipt->reference,
                    'provider' => $connection->provider,
                    'connection_reference' => $connection->reference,
                ],
            ),
        );
        $allocation = $this->operations->allocate(
            new TreasuryPositionAllocationData(
                operationReference: 'historical-position-allocation:'.$scope,
                sourcePositionReference: $source->positionReference,
                destinationPositionReference: $destination->positionReference,
                amountMinor: $receipt->net_amount_minor,
                currency: $receipt->currency,
                idempotencyKey: 'historical-position-allocation-key:'.$scope,
                externalReference: $recognition->operationReference,
                metadata: [
                    'source' => 'historical_standing_funding_backfill',
                    'account_funding_receipt_reference' => $receipt->reference,
                    'provider' => $connection->provider,
                    'connection_reference' => $connection->reference,
                ],
            ),
        );
        $recovery = $this->recoveries->recover(
            $account,
            $receipt->net_amount_minor,
            [
                'source' => 'historical_standing_funding_backfill',
                'account_funding_receipt_reference' => $receipt->reference,
                'treasury_position_allocation_reference' => $allocation->operationReference,
                'reverses_legacy_transaction_id' => $legacyCredit->getKey(),
                'reverses_legacy_transaction_uuid' => $legacyCredit->uuid,
            ],
        );

        if (
            $recovery->recoveredAmountMinor !== $receipt->net_amount_minor
            || $recovery->outstandingAmountMinor !== 0
            || $recovery->walletTransactionId === null
            || $recovery->walletTransactionUuid === null
            || $allocation->destinationTransactionId === null
            || $allocation->destinationTransactionUuid === null
        ) {
            throw new TreasuryConfigurationException(
                "Historical Account Funding Receipt [{$receipt->reference}] could not be recovered exactly.",
            );
        }

        AccountFundingReceipt::query()
            ->whereKey($receipt->getKey())
            ->update([
                'wallet_transaction_id' => $allocation->destinationTransactionId,
                'wallet_transaction_uuid' => $allocation->destinationTransactionUuid,
                'metadata' => array_merge($receipt->metadata ?? [], [
                    'treasury_source_position_reference' => $source->positionReference,
                    'treasury_destination_position_reference' => $destination->positionReference,
                    'treasury_position_recognition_reference' => $recognition->operationReference,
                    'treasury_position_allocation_reference' => $allocation->operationReference,
                    'treasury_position_transfer_uuid' => $allocation->transferUuid,
                    'legacy_wallet_credit_transaction_id' => (int) $legacyCredit->getKey(),
                    'legacy_wallet_credit_transaction_uuid' => (string) $legacyCredit->uuid,
                    'legacy_wallet_recovery_transaction_id' => $recovery->walletTransactionId,
                    'legacy_wallet_recovery_transaction_uuid' => $recovery->walletTransactionUuid,
                    'historical_position_backfilled_at' => now()->toRfc3339String(),
                ]),
            ]);
    }

    /**
     * @return array{object, Transaction}
     */
    private function validateReceipt(
        AccountFundingReceipt $receipt,
        TreasuryProviderConnectionData $connection,
    ): array {
        $inventoryOperation = TreasuryInventoryOperation::query()
            ->with('destinationInventory')
            ->where('operation_reference', $receipt->treasury_operation_reference)
            ->first();
        $account = $this->accounts->resolve($receipt->account_reference);
        $legacyCredit = Transaction::query()
            ->whereKey($receipt->wallet_transaction_id)
            ->first();
        $observation = $receipt->providerFundingObservation;
        $valid = $receipt->status === AccountFundingReceiptStatus::Settled
            && $receipt->net_amount_minor > 0
            && $receipt->provider_code === $connection->provider
            && $receipt->currency === $connection->currency
            && $inventoryOperation?->operation_type
                === TreasuryInventoryOperationType::Recognition
            && $inventoryOperation->amount_minor === $receipt->net_amount_minor
            && $inventoryOperation->currency === $receipt->currency
            && $inventoryOperation->destinationInventory?->inventory_reference
                === $connection->inventoryReference
            && $observation !== null
            && $observation->provider_code === $receipt->provider_code
            && $observation->net_amount_minor === $receipt->net_amount_minor
            && $observation->currency === $receipt->currency
            && $legacyCredit?->type === Transaction::TYPE_DEPOSIT
            && $legacyCredit->confirmed
            && $legacyCredit->amountInt === $receipt->net_amount_minor
            && (int) $legacyCredit->wallet_id === (int) data_get($account, 'id');

        if (! $valid) {
            throw new TreasuryConfigurationException(
                "Historical Account Funding Receipt [{$receipt->reference}] failed evidence validation.",
            );
        }

        return [$account, $legacyCredit];
    }

    private function connection(
        string $connectionReference,
    ): TreasuryProviderConnectionData {
        $matches = $this->connections->active([trim($connectionReference)]);

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                'Historical funding backfill requires exactly one active Treasury connection.',
            );
        }

        return $matches[0];
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function position(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose,
        ));

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                "Historical funding backfill requires one {$purpose->value} Position.",
            );
        }

        return $matches[0];
    }

    /**
     * @param  Collection<int, AccountFundingReceipt>  $receipts
     */
    private function result(
        string $status,
        TreasuryProviderConnectionData $connection,
        Collection $receipts,
        int $backfilledCount,
    ): HistoricalStandingFundingBackfillData {
        return new HistoricalStandingFundingBackfillData(
            status: $status,
            connectionReference: $connection->reference,
            provider: $connection->provider,
            currency: $connection->currency,
            candidateCount: $receipts->count(),
            backfilledCount: $backfilledCount,
            amountMinor: (int) $receipts->sum('net_amount_minor'),
            receiptReferences: $receipts
                ->pluck('reference')
                ->map(static fn (mixed $reference): string => (string) $reference)
                ->values()
                ->all(),
        );
    }
}
