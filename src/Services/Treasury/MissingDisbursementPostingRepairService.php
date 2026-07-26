<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryInventoryOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Data\Treasury\MissingDisbursementPostingRepairData;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningBalanceConnectionData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\TreasuryOpeningBalanceStatus;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\DisbursementReconciliation;

final readonly class MissingDisbursementPostingRepairService
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryOpeningBalanceReconciliationService $reconciliation,
        private TreasuryInventoryPositionReadModelContract $inventories,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryInventoryOperationContract $inventoryOperations,
        private TreasuryPositionOperationContract $positionOperations,
        private SystemUserResolverContract $systemPrincipal,
    ) {}

    /**
     * @param  list<int>  $reconciliationIds
     */
    public function inspect(
        string $connectionReference,
        array $reconciliationIds = [],
    ): MissingDisbursementPostingRepairData {
        return $this->plan($connectionReference, $reconciliationIds);
    }

    /**
     * @param  list<int>  $reconciliationIds
     */
    public function repair(
        string $connectionReference,
        array $reconciliationIds,
    ): MissingDisbursementPostingRepairData {
        $reconciliationIds = $this->normalizedIds($reconciliationIds);

        if ($reconciliationIds === []) {
            throw new TreasuryConfigurationException(
                'A committed missing-disbursement repair requires explicit reconciliation IDs from a dry run.',
            );
        }

        $connection = $this->connection($connectionReference);
        $lock = Cache::lock(
            'x-change:treasury:opening-balance:'.hash('sha256', $connection->reference),
            max(1, (int) config('x-change.treasury.reconciliation_lock_seconds', 60)),
        );

        return $lock->block(
            max(0, (int) config('x-change.treasury.reconciliation_lock_wait_seconds', 5)),
            function () use (
                $connection,
                $reconciliationIds,
            ): MissingDisbursementPostingRepairData {
                $plan = $this->plan($connection->reference, $reconciliationIds);

                if ($plan->status === 'already_repaired') {
                    return $plan;
                }

                if ($plan->status !== 'dry_run') {
                    throw new TreasuryConfigurationException(
                        'The missing-disbursement repair plan is not eligible for commit.',
                    );
                }

                return DB::transaction(function () use (
                    $connection,
                    $plan,
                    $reconciliationIds,
                ): MissingDisbursementPostingRepairData {
                    $this->lockConnection($connection);
                    $reconciliations = DisbursementReconciliation::query()
                        ->lockForUpdate()
                        ->whereKey($reconciliationIds)
                        ->orderBy('id')
                        ->get();

                    if ($reconciliations->count() !== count($reconciliationIds)) {
                        throw new TreasuryConfigurationException(
                            'One or more selected disbursement reconciliations no longer exist.',
                        );
                    }

                    $baseline = $this->openingBaseline($connection);
                    $vouchers = $this->vouchers($reconciliations);
                    $system = $this->system();

                    foreach ($reconciliations as $reconciliation) {
                        $this->validateEvidence(
                            $reconciliation,
                            $vouchers,
                            $system,
                            $connection,
                            $baseline,
                        );

                        if ($this->postingState($reconciliation, $connection) !== 'missing') {
                            throw new TreasuryConfigurationException(
                                "Disbursement reconciliation [{$reconciliation->getKey()}] is no longer missing its Treasury posting.",
                            );
                        }
                    }

                    $this->assertInternalControl(
                        $connection,
                        $plan->inventoryBalanceMinor,
                        $plan->positionBalanceMinor,
                    );
                    $source = $this->repairSourcePosition(
                        $connection,
                        $plan->principalAmountMinor,
                    );
                    $settlementSource = $source->purpose
                        === TreasuryPositionPurpose::AccountFundingReserve
                        ? $this->systemPosition(
                            $connection,
                            TreasuryPositionPurpose::PayCodeReserve,
                        )
                        : $source;

                    $inventoryReferences = [];
                    $positionReferences = [];

                    foreach ($reconciliations as $reconciliation) {
                        [
                            $inventoryReference,
                            $positionReference,
                            $scope,
                            $reservationReference,
                        ] =
                            $this->operationReferences(
                                $reconciliation,
                                $connection,
                            );
                        $amountMinor = $this->amountMinor(
                            $reconciliation,
                            $connection,
                        );
                        $metadata = $this->metadata(
                            $reconciliation,
                            $connection,
                            $amountMinor,
                            $scope,
                        );

                        if (
                            $source->purpose
                            === TreasuryPositionPurpose::AccountFundingReserve
                        ) {
                            $this->positionOperations->reserveAccountFunding(
                                new TreasuryPositionReservationData(
                                    operationReference: $reservationReference,
                                    sourcePositionReference: $source->positionReference,
                                    destinationPositionReference: $settlementSource->positionReference,
                                    amountMinor: $amountMinor,
                                    currency: $connection->currency,
                                    idempotencyKey: 'missing-disbursement-position-reservation-key:'.$scope,
                                    externalReference: $connection->provider.':'.$reconciliation->provider_transaction_id,
                                    metadata: $metadata,
                                ),
                            );
                        }

                        $positionOperation = $this->positionOperations
                            ->derecognize(new TreasuryPositionDerecognitionData(
                                operationReference: $positionReference,
                                sourcePositionReference: $settlementSource->positionReference,
                                amountMinor: $amountMinor,
                                currency: $connection->currency,
                                idempotencyKey: 'missing-disbursement-position-derecognition-key:'.$scope,
                                externalReference: $connection->provider.':'.$reconciliation->provider_transaction_id,
                                metadata: $metadata,
                            ));
                        $inventoryOperation = $this->inventoryOperations
                            ->adjust(new TreasuryInventoryAdjustmentData(
                                operationReference: $inventoryReference,
                                inventoryReference: $connection->inventoryReference,
                                deltaAmountMinor: -$amountMinor,
                                currency: $connection->currency,
                                status: 'requested',
                                idempotencyKey: 'missing-disbursement-inventory-adjustment-key:'.$scope,
                                effectiveAt: $reconciliation->completed_at?->toRfc3339String(),
                                externalReference: $connection->provider.':'.$reconciliation->provider_transaction_id,
                                metadata: $metadata,
                            ));
                        $positionReferences[] = $positionOperation->operationReference;
                        $inventoryReferences[] = $inventoryOperation->operationReference;
                    }

                    $this->assertInternalControl(
                        $connection,
                        $plan->providerBalanceMinor,
                        $plan->providerBalanceMinor,
                    );

                    return new MissingDisbursementPostingRepairData(
                        status: 'repaired',
                        connectionReference: $connection->reference,
                        provider: $connection->provider,
                        currency: $connection->currency,
                        providerBalanceMinor: $plan->providerBalanceMinor,
                        inventoryBalanceMinor: $plan->providerBalanceMinor,
                        positionBalanceMinor: $plan->providerBalanceMinor,
                        deficitMinor: 0,
                        candidateCount: count($reconciliationIds),
                        repairedCount: count($reconciliationIds),
                        principalAmountMinor: $plan->principalAmountMinor,
                        reconciliationIds: $reconciliationIds,
                        inventoryOperationReferences: $inventoryReferences,
                        positionOperationReferences: $positionReferences,
                    );
                }, attempts: 5);
            },
        );
    }

    /**
     * @param  list<int>  $selectedIds
     */
    private function plan(
        string $connectionReference,
        array $selectedIds,
    ): MissingDisbursementPostingRepairData {
        $connection = $this->connection($connectionReference);
        $observation = $this->observation($connection);
        $this->assertObservationControl($observation);
        $baseline = $this->openingBaseline($connection);
        $reconciliations = $this->reconciliations($connection, $baseline);
        $vouchers = $this->vouchers($reconciliations);
        $system = $this->system();
        $missing = [];
        $repaired = [];

        foreach ($reconciliations as $reconciliation) {
            $state = $this->postingState($reconciliation, $connection);

            if ($state === 'missing') {
                $this->validateEvidence(
                    $reconciliation,
                    $vouchers,
                    $system,
                    $connection,
                    $baseline,
                );
                $missing[] = $reconciliation;
            } elseif ($state === 'repaired') {
                $this->validateEvidence(
                    $reconciliation,
                    $vouchers,
                    $system,
                    $connection,
                    $baseline,
                );
                $repaired[(int) $reconciliation->getKey()] = $reconciliation;
            }
        }

        $selectedIds = $this->normalizedIds($selectedIds);

        if ($selectedIds !== []) {
            $selectedRepaired = array_values(array_intersect(
                $selectedIds,
                array_keys($repaired),
            ));

            if (
                count($selectedRepaired) === count($selectedIds)
                && $observation->differenceMinor === 0
            ) {
                return $this->result(
                    status: 'already_repaired',
                    connection: $connection,
                    observation: $observation,
                    reconciliations: array_values(array_map(
                        static fn (int $id): DisbursementReconciliation => $repaired[$id],
                        $selectedIds,
                    )),
                    repairedCount: count($selectedIds),
                );
            }

            $missingIds = array_map(
                static fn (DisbursementReconciliation $reconciliation): int => (int) $reconciliation->getKey(),
                $missing,
            );

            if ($selectedIds !== $missingIds) {
                throw new TreasuryConfigurationException(
                    'Explicit reconciliation IDs must exactly match the current dry-run candidates.',
                );
            }
        }

        if ($observation->differenceMinor === 0 && $missing === []) {
            return $this->result(
                status: 'no_candidates',
                connection: $connection,
                observation: $observation,
                reconciliations: [],
            );
        }

        if (
            $observation->status !== TreasuryOpeningBalanceStatus::ReviewRequired
            || $observation->reason !== 'provider-balance-below-internal-attribution'
            || $observation->differenceMinor >= 0
        ) {
            throw new TreasuryConfigurationException(
                'The connection does not have an eligible provider-below-Treasury deficit.',
            );
        }

        if ($missing === []) {
            throw new TreasuryConfigurationException(
                'No authoritative missing disbursement postings explain the Treasury deficit.',
            );
        }

        $deficitMinor = abs($observation->differenceMinor);
        $principalAmountMinor = array_sum(array_map(
            fn (DisbursementReconciliation $reconciliation): int => $this->amountMinor(
                $reconciliation,
                $connection,
            ),
            $missing,
        ));

        if ($principalAmountMinor !== $deficitMinor) {
            throw new TreasuryConfigurationException(
                "Missing disbursement principal [{$principalAmountMinor}] does not exactly match the provider-to-Treasury deficit [{$deficitMinor}].",
            );
        }

        $this->repairSourcePosition($connection, $principalAmountMinor);

        return $this->result(
            status: 'dry_run',
            connection: $connection,
            observation: $observation,
            reconciliations: $missing,
        );
    }

    private function connection(
        string $connectionReference,
    ): TreasuryProviderConnectionData {
        $matches = $this->connections->active([trim($connectionReference)]);

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                'Missing disbursement repair requires exactly one active Treasury connection.',
            );
        }

        return $matches[0];
    }

    private function observation(
        TreasuryProviderConnectionData $connection,
    ): TreasuryOpeningBalanceConnectionData {
        $observations = $this->reconciliation
            ->observe([$connection->reference])
            ->connections;

        if (count($observations) !== 1) {
            throw new TreasuryConfigurationException(
                'Missing disbursement repair requires one provider balance observation.',
            );
        }

        return $observations[0];
    }

    private function assertObservationControl(
        TreasuryOpeningBalanceConnectionData $observation,
    ): void {
        if (
            $observation->inventoryBalanceMinor
                !== $observation->positionBalanceMinor
        ) {
            throw new TreasuryConfigurationException(
                'Missing disbursement repair requires balanced Inventory and Positions.',
            );
        }
    }

    private function openingBaseline(
        TreasuryProviderConnectionData $connection,
    ): TreasuryInventoryOperation {
        $baseline = TreasuryInventoryOperation::query()
            ->where('operation_type', TreasuryInventoryOperationType::Recognition)
            ->where('metadata->source', 'provider_balance_reconciliation')
            ->whereHas(
                'destinationInventory',
                fn ($query) => $query->where(
                    'inventory_reference',
                    $connection->inventoryReference,
                ),
            )
            ->latest('id')
            ->first();

        if (! $baseline instanceof TreasuryInventoryOperation) {
            throw new TreasuryConfigurationException(
                'Missing disbursement repair requires an opening provider-balance recognition.',
            );
        }

        return $baseline;
    }

    /**
     * @return Collection<int, DisbursementReconciliation>
     */
    private function reconciliations(
        TreasuryProviderConnectionData $connection,
        TreasuryInventoryOperation $baseline,
    ): Collection {
        return DisbursementReconciliation::query()
            ->where('provider', $connection->provider)
            ->where('currency', $connection->currency)
            ->where('claim_type', 'redeem')
            ->where('status', 'succeeded')
            ->where('needs_review', false)
            ->whereNotNull('provider_transaction_id')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $baseline->created_at)
            ->orderBy('id')
            ->get()
            ->filter(
                fn (DisbursementReconciliation $reconciliation): bool => $this
                    ->hasPostBaselineProviderEvidence(
                        $reconciliation,
                        $baseline,
                    ),
            )
            ->values();
    }

    private function hasPostBaselineProviderEvidence(
        DisbursementReconciliation $reconciliation,
        TreasuryInventoryOperation $baseline,
    ): bool {
        try {
            return $this->providerObservedAt($reconciliation->raw_response)
                ->greaterThanOrEqualTo(
                    CarbonImmutable::instance($baseline->created_at),
                );
        } catch (TreasuryConfigurationException) {
            return false;
        }
    }

    /**
     * @param  Collection<int, DisbursementReconciliation>  $reconciliations
     * @return Collection<int, Voucher>
     */
    private function vouchers(Collection $reconciliations): Collection
    {
        return Voucher::query()
            ->whereKey($reconciliations->pluck('voucher_id')->all())
            ->get()
            ->keyBy('id');
    }

    private function system(): Model
    {
        $system = $this->systemPrincipal->resolve();

        if (! $system instanceof Model) {
            throw new TreasuryConfigurationException(
                'Missing disbursement repair requires a persisted system principal.',
            );
        }

        return $system;
    }

    /**
     * @param  Collection<int, Voucher>  $vouchers
     */
    private function validateEvidence(
        DisbursementReconciliation $reconciliation,
        Collection $vouchers,
        Model $system,
        TreasuryProviderConnectionData $connection,
        TreasuryInventoryOperation $baseline,
    ): void {
        $voucher = $vouchers->get($reconciliation->voucher_id);
        $raw = $reconciliation->raw_response;
        $providerObservedAt = $this->providerObservedAt($raw);
        $amountMinor = $this->amountMinor($reconciliation, $connection);
        $rawAmountMinor = data_get($raw, 'amount.num');
        $rawCurrency = data_get($raw, 'amount.cur');
        $rawStatus = mb_strtolower(trim((string) data_get($raw, 'status')));
        $duplicateEvidence = DisbursementReconciliation::query()
            ->where('provider', $connection->provider)
            ->where(
                'provider_transaction_id',
                $reconciliation->provider_transaction_id,
            )
            ->count();
        $valid = $voucher instanceof Voucher
            && $voucher->getKey() === $reconciliation->voucher_id
            && $voucher->code === $reconciliation->voucher_code
            && $voucher->owner_type === $system->getMorphClass()
            && (string) $voucher->owner_id === (string) $system->getKey()
            && $reconciliation->claim_type === 'redeem'
            && $reconciliation->provider === $connection->provider
            && $reconciliation->currency === $connection->currency
            && $reconciliation->status === 'succeeded'
            && ! $reconciliation->needs_review
            && $reconciliation->completed_at !== null
            && filled($reconciliation->provider_transaction_id)
            && $duplicateEvidence === 1
            && is_array($raw)
            && (string) data_get($raw, 'transaction_id')
                === (string) $reconciliation->provider_transaction_id
            && in_array($rawStatus, ['completed', 'settled', 'succeeded'], true)
            && $rawCurrency === $connection->currency
            && is_numeric($rawAmountMinor)
            && (int) $rawAmountMinor === $amountMinor
            && $providerObservedAt->greaterThanOrEqualTo(
                CarbonImmutable::instance($baseline->created_at),
            )
            && (int) round(
                ((float) data_get($voucher->metadata, 'disbursement.amount')) * 100,
            ) === $amountMinor
            && data_get($voucher->metadata, 'disbursement.currency')
                === $connection->currency
            && data_get($voucher->metadata, 'disbursement.gateway')
                === $connection->provider
            && (string) data_get(
                $voucher->metadata,
                'disbursement.transaction_id',
            ) === (string) $reconciliation->provider_transaction_id;

        if (! $valid) {
            throw new TreasuryConfigurationException(
                "Disbursement reconciliation [{$reconciliation->getKey()}] failed authoritative evidence validation.",
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    private function providerObservedAt(?array $raw): CarbonImmutable
    {
        $value = data_get($raw, 'date')
            ?? data_get($raw, 'created_at')
            ?? data_get($raw, 'updated');

        if (! is_string($value) || trim($value) === '') {
            throw new TreasuryConfigurationException(
                'Disbursement evidence has no authoritative provider timestamp.',
            );
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            throw new TreasuryConfigurationException(
                'Disbursement evidence has an invalid provider timestamp.',
            );
        }
    }

    private function amountMinor(
        DisbursementReconciliation $reconciliation,
        TreasuryProviderConnectionData $connection,
    ): int {
        $amountMinor = (int) round(
            ((float) $reconciliation->amount)
            * (10 ** $connection->decimalPlaces),
        );

        if ($amountMinor <= 0) {
            throw new TreasuryConfigurationException(
                "Disbursement reconciliation [{$reconciliation->getKey()}] has an invalid principal amount.",
            );
        }

        return $amountMinor;
    }

    private function postingState(
        DisbursementReconciliation $reconciliation,
        TreasuryProviderConnectionData $connection,
    ): string {
        [$inventoryReference, $positionReference] = $this->operationReferences(
            $reconciliation,
            $connection,
        );
        $repairInventory = $this->inventories->operationExists(
            $inventoryReference,
        );
        $repairPosition = $this->positions->operationExists(
            $positionReference,
        );
        $externalReference = $connection->provider.':'
            .$reconciliation->provider_transaction_id;
        $anyInventory = TreasuryInventoryOperation::query()
            ->where('operation_type', TreasuryInventoryOperationType::Adjustment)
            ->where(function ($query) use (
                $externalReference,
                $reconciliation,
            ): void {
                $query->where(
                    'metadata->disbursement_reconciliation_id',
                    (int) $reconciliation->getKey(),
                )->orWhere('external_reference', $externalReference);
            })
            ->exists();
        $anyPosition = TreasuryPositionOperation::query()
            ->where('operation_type', TreasuryPositionOperationType::Derecognition)
            ->where(function ($query) use (
                $externalReference,
                $reconciliation,
            ): void {
                $query->where(
                    'metadata->disbursement_reconciliation_id',
                    (int) $reconciliation->getKey(),
                )->orWhere('external_reference', $externalReference);
            })
            ->exists();

        if ($repairInventory xor $repairPosition) {
            throw new TreasuryConfigurationException(
                "Disbursement reconciliation [{$reconciliation->getKey()}] has a partially applied repair.",
            );
        }

        if ($repairInventory && $repairPosition) {
            return 'repaired';
        }

        if ($anyInventory xor $anyPosition) {
            throw new TreasuryConfigurationException(
                "Disbursement reconciliation [{$reconciliation->getKey()}] has a partial Treasury posting.",
            );
        }

        return $anyInventory && $anyPosition ? 'posted' : 'missing';
    }

    /**
     * @return array{string, string, string, string}
     */
    private function operationReferences(
        DisbursementReconciliation $reconciliation,
        TreasuryProviderConnectionData $connection,
    ): array {
        $scope = hash('sha256', implode('|', [
            $connection->provider,
            $connection->reference,
            (string) $reconciliation->getKey(),
            (string) $reconciliation->provider_transaction_id,
            (string) $this->amountMinor($reconciliation, $connection),
        ]));

        return [
            'missing-disbursement-inventory-adjustment:'.$scope,
            'missing-disbursement-position-derecognition:'.$scope,
            $scope,
            'missing-disbursement-position-reservation:'.$scope,
        ];
    }

    private function repairSourcePosition(
        TreasuryProviderConnectionData $connection,
        int $requiredAmountMinor,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $this->positions->forConnection(
                $connection->provider,
                $connection->reference,
                $connection->currency,
            ),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->principalReference === trim((string) config(
                    'x-change.treasury.principal_reference',
                ))
                && in_array($position->purpose, [
                    TreasuryPositionPurpose::LegacyUnattributed,
                    TreasuryPositionPurpose::AccountFundingReserve,
                ], true),
        ));

        foreach ([
            TreasuryPositionPurpose::LegacyUnattributed,
            TreasuryPositionPurpose::AccountFundingReserve,
        ] as $purpose) {
            $position = collect($matches)->first(
                static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose
                    && $position->balanceMinor >= $requiredAmountMinor,
            );

            if ($position instanceof TreasuryPositionData) {
                return $position;
            }
        }

        throw new TreasuryConfigurationException(
            'No eligible system Treasury position can cover the exact repair amount.',
        );
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
                "Missing disbursement repair requires one system {$purpose->value} Position.",
            );
        }

        return $matches[0];
    }

    private function lockConnection(
        TreasuryProviderConnectionData $connection,
    ): void {
        $inventory = TreasuryInventory::query()
            ->where('inventory_reference', $connection->inventoryReference)
            ->lockForUpdate()
            ->first();
        $positions = TreasuryPosition::query()
            ->where('provider', $connection->provider)
            ->where('connection_reference', $connection->reference)
            ->where('currency', $connection->currency)
            ->where('status', 'active')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if (! $inventory instanceof TreasuryInventory || $positions->isEmpty()) {
            throw new TreasuryConfigurationException(
                'Missing disbursement repair could not lock Treasury control records.',
            );
        }
    }

    private function assertInternalControl(
        TreasuryProviderConnectionData $connection,
        int $expectedInventoryMinor,
        int $expectedPositionsMinor,
    ): void {
        $inventory = $this->inventories->find($connection->inventoryReference);
        $positionBalanceMinor = array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            array_values(array_filter(
                $this->positions->forConnection(
                    $connection->provider,
                    $connection->reference,
                    $connection->currency,
                ),
                static fn (TreasuryPositionData $position): bool => $position->status === 'active',
            )),
        ));

        if (
            $inventory === null
            || $inventory->balanceMinor !== $expectedInventoryMinor
            || $positionBalanceMinor !== $expectedPositionsMinor
        ) {
            throw new TreasuryConfigurationException(
                'Treasury controls changed while the missing-disbursement repair was running.',
            );
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function normalizedIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0,
        )));
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<DisbursementReconciliation>  $reconciliations
     */
    private function result(
        string $status,
        TreasuryProviderConnectionData $connection,
        TreasuryOpeningBalanceConnectionData $observation,
        array $reconciliations,
        int $repairedCount = 0,
    ): MissingDisbursementPostingRepairData {
        $ids = array_map(
            static fn (DisbursementReconciliation $reconciliation): int => (int) $reconciliation->getKey(),
            $reconciliations,
        );
        $principalAmountMinor = array_sum(array_map(
            fn (DisbursementReconciliation $reconciliation): int => $this->amountMinor(
                $reconciliation,
                $connection,
            ),
            $reconciliations,
        ));

        return new MissingDisbursementPostingRepairData(
            status: $status,
            connectionReference: $connection->reference,
            provider: $connection->provider,
            currency: $connection->currency,
            providerBalanceMinor: $observation->providerBalanceMinor,
            inventoryBalanceMinor: $observation->inventoryBalanceMinor,
            positionBalanceMinor: $observation->positionBalanceMinor,
            deficitMinor: max(0, -$observation->differenceMinor),
            candidateCount: count($ids),
            repairedCount: $repairedCount,
            principalAmountMinor: $principalAmountMinor,
            reconciliationIds: $ids,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(
        DisbursementReconciliation $reconciliation,
        TreasuryProviderConnectionData $connection,
        int $amountMinor,
        string $scope,
    ): array {
        return [
            'source' => 'missing_disbursement_posting_repair',
            'repair_scope' => $scope,
            'voucher_id' => (int) $reconciliation->voucher_id,
            'disbursement_reconciliation_id' => (int) $reconciliation->getKey(),
            'provider' => $connection->provider,
            'connection_reference' => $connection->reference,
            'provider_principal_amount_minor' => $amountMinor,
            'configured_fee_excluded' => true,
        ];
    }
}
