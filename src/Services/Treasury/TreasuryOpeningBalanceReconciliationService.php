<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\ProviderBalanceReader;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceObservationData;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningBalanceConnectionData;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningBalanceReconciliationData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\TreasuryOpeningBalanceStatus;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Exceptions\TreasuryPreflightFailed;

final class TreasuryOpeningBalanceReconciliationService
{
    /** @var array<string, ProviderBalanceReader> */
    private array $readers = [];

    /**
     * @param  iterable<ProviderBalanceReader>  $readers
     */
    public function __construct(
        private readonly TreasuryPreflightService $preflight,
        private readonly TreasuryProvisioningService $provisioning,
        private readonly TreasuryInventoryOperationContract $inventoryOperations,
        private readonly TreasuryInventoryRegistrationService $inventoryRegistration,
        private readonly TreasuryInventoryPositionReadModelContract $inventories,
        private readonly TreasuryPositionOperationContract $positionOperations,
        private readonly TreasuryPositionReadModelContract $positions,
        iterable $readers,
    ) {
        foreach ($readers as $reader) {
            $provider = mb_strtolower(trim($reader->providerCode()));

            if (isset($this->readers[$provider])) {
                throw new TreasuryConfigurationException(
                    "Multiple balance readers are registered for provider [{$provider}].",
                );
            }

            $this->readers[$provider] = $reader;
        }
    }

    /**
     * @param  list<string>  $connectionReferences
     */
    public function reconcile(
        array $connectionReferences = [],
    ): TreasuryOpeningBalanceReconciliationData {
        $preflight = $this->preflight->run($connectionReferences);

        if (! $preflight->passes()) {
            throw new TreasuryPreflightFailed(
                'Required Treasury provider connections did not pass opening reconciliation preflight.',
            );
        }

        $this->provisioning->provision($connectionReferences);
        $results = [];

        foreach ($preflight->connections as $result) {
            if (! $result->ready) {
                continue;
            }

            $connection = $result->connection;
            $lock = Cache::lock(
                'x-change:treasury:opening-balance:'.hash('sha256', $connection->reference),
                max(1, (int) config('x-change.treasury.reconciliation_lock_seconds', 60)),
            );
            $results[] = $lock->block(
                max(0, (int) config('x-change.treasury.reconciliation_lock_wait_seconds', 5)),
                fn (): TreasuryOpeningBalanceConnectionData => $this->reconcileConnection($connection),
            );
        }

        return new TreasuryOpeningBalanceReconciliationData($results);
    }

    public function simulateDeposit(
        string $connectionReference,
        int $amountMinor,
        string $simulationReference,
    ): TreasuryOpeningBalanceConnectionData {
        if (
            ! (bool) config('x-change.treasury.simulator.enabled', false)
            || ! in_array(
                app()->environment(),
                (array) config('x-change.treasury.simulator.allowed_environments', []),
                true,
            )
        ) {
            throw new TreasuryConfigurationException(
                'Treasury provider deposit simulation is unavailable in this environment.',
            );
        }

        $simulationReference = trim($simulationReference);

        if ($amountMinor <= 0 || $simulationReference === '') {
            throw new TreasuryConfigurationException(
                'Treasury provider deposit simulation requires a positive amount and reference.',
            );
        }

        $preflight = $this->preflight->run([trim($connectionReference)]);

        if (
            ! $preflight->passes()
            || count($preflight->connections) !== 1
            || ! $preflight->connections[0]->ready
        ) {
            throw new TreasuryPreflightFailed(
                'Treasury provider connection did not pass simulation preflight.',
            );
        }

        $connection = $preflight->connections[0]->connection;
        $this->provisioning->provision([$connection->reference]);
        $lock = Cache::lock(
            'x-change:treasury:opening-balance:'.hash('sha256', $connection->reference),
            max(1, (int) config('x-change.treasury.reconciliation_lock_seconds', 60)),
        );

        return $lock->block(
            max(0, (int) config('x-change.treasury.reconciliation_lock_wait_seconds', 5)),
            function () use (
                $amountMinor,
                $connection,
                $simulationReference,
            ): TreasuryOpeningBalanceConnectionData {
                $simulationScope = hash('sha256', implode('|', [
                    $connection->reference,
                    $simulationReference,
                    (string) $amountMinor,
                ]));
                $evidenceReference = 'simulation:'.$simulationScope;
                $operationScope = hash('sha256', implode('|', [
                    $connection->reference,
                    $evidenceReference,
                    (string) $amountMinor,
                ]));
                $inventoryOperationReference = 'opening-inventory-recognition:'.$operationScope;
                $positionOperationReference = 'opening-position-recognition:'.$operationScope;

                if (
                    $this->inventories->operationExists($inventoryOperationReference)
                    && $this->positions->operationExists($positionOperationReference)
                ) {
                    return $this->currentConnectionResult(
                        $connection,
                        $evidenceReference,
                    );
                }

                $positionBalanceMinor = $this->connectionPositionBalance($connection);
                $observation = new ProviderBalanceObservationData(
                    provider: $connection->provider,
                    connectionReference: $connection->reference,
                    settlementResourceReference: $connection->settlementResourceReference,
                    amountMinor: $positionBalanceMinor + $amountMinor,
                    currency: $connection->currency,
                    observedAt: DateTimeImmutable::createFromInterface(now()),
                    evidenceReference: $evidenceReference,
                );

                return $this->reconcileObservation($connection, $observation);
            },
        );
    }

    private function reconcileConnection(
        TreasuryProviderConnectionData $connection,
    ): TreasuryOpeningBalanceConnectionData {
        $reader = $this->readers[$connection->provider] ?? null;

        if (! $reader instanceof ProviderBalanceReader) {
            throw new TreasuryConfigurationException(
                "Provider [{$connection->provider}] has no registered balance reader.",
            );
        }

        $observation = $reader->readBalance(new ProviderBalanceRequestData(
            provider: $connection->provider,
            connectionReference: $connection->reference,
            settlementResourceReference: $connection->settlementResourceReference,
            currency: $connection->currency,
        ));

        return $this->reconcileObservation($connection, $observation);
    }

    private function reconcileObservation(
        TreasuryProviderConnectionData $connection,
        ProviderBalanceObservationData $observation,
    ): TreasuryOpeningBalanceConnectionData {
        $this->assertObservationMatches($connection, $observation);
        $this->registerInventory($connection);
        $inventory = $this->inventories->find($connection->inventoryReference);

        if ($inventory === null) {
            throw new TreasuryConfigurationException(
                "Treasury Inventory [{$connection->inventoryReference}] was not registered.",
            );
        }

        $positions = array_values(array_filter(
            $this->positions->forConnection(
                $connection->provider,
                $connection->reference,
                $connection->currency,
            ),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->settlementResourceReference === $connection->settlementResourceReference,
        ));
        $positionBalanceMinor = array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            $positions,
        ));
        $inventoryBalanceMinor = $inventory->balanceMinor;

        if ($inventoryBalanceMinor !== $positionBalanceMinor) {
            return $this->reviewRequired(
                $connection,
                $observation,
                $inventoryBalanceMinor,
                $positionBalanceMinor,
                'internal-inventory-position-mismatch',
            );
        }

        $differenceMinor = $observation->amountMinor - $positionBalanceMinor;

        if ($differenceMinor < 0) {
            return $this->reviewRequired(
                $connection,
                $observation,
                $inventoryBalanceMinor,
                $positionBalanceMinor,
                'provider-balance-below-internal-attribution',
            );
        }

        if ($differenceMinor === 0) {
            return new TreasuryOpeningBalanceConnectionData(
                connectionReference: $connection->reference,
                provider: $connection->provider,
                currency: $connection->currency,
                status: TreasuryOpeningBalanceStatus::Reconciled,
                providerBalanceMinor: $observation->amountMinor,
                inventoryBalanceMinor: $inventoryBalanceMinor,
                positionBalanceMinor: $positionBalanceMinor,
                differenceMinor: 0,
                evidenceReference: $observation->evidenceReference,
                observedAt: $observation->observedAt->format(DATE_ATOM),
            );
        }

        $unattributed = $this->unattributedPosition($positions, $connection);
        $scope = hash('sha256', implode('|', [
            $connection->reference,
            $observation->evidenceReference,
            (string) $differenceMinor,
        ]));

        [$inventoryRecognition, $positionRecognition] = DB::transaction(
            function () use (
                $connection,
                $differenceMinor,
                $observation,
                $scope,
                $unattributed,
            ): array {
                $inventoryRecognition = $this->inventoryOperations->recognize(
                    new TreasuryInventoryRecognitionData(
                        operationReference: 'opening-inventory-recognition:'.$scope,
                        inventoryReference: $connection->inventoryReference,
                        settlementResourceReference: $connection->settlementResourceReference,
                        amountMinor: $differenceMinor,
                        currency: $connection->currency,
                        status: 'requested',
                        idempotencyKey: 'opening-inventory-recognition-key:'.$scope,
                        effectiveAt: $observation->observedAt->format(DATE_ATOM),
                        externalReference: $observation->evidenceReference,
                        metadata: [
                            'source' => 'provider_balance_reconciliation',
                            'provider' => $connection->provider,
                            'connection_reference' => $connection->reference,
                        ],
                    ),
                );
                $positionRecognition = $this->positionOperations->recognize(
                    new TreasuryPositionRecognitionData(
                        operationReference: 'opening-position-recognition:'.$scope,
                        destinationPositionReference: $unattributed->positionReference,
                        amountMinor: $differenceMinor,
                        currency: $connection->currency,
                        idempotencyKey: 'opening-position-recognition-key:'.$scope,
                        externalReference: $inventoryRecognition->operationReference,
                        metadata: [
                            'source' => 'provider_balance_reconciliation',
                            'provider' => $connection->provider,
                            'connection_reference' => $connection->reference,
                            'provider_evidence_reference' => $observation->evidenceReference,
                        ],
                    ),
                );

                return [$inventoryRecognition, $positionRecognition];
            },
            attempts: 5,
        );

        return new TreasuryOpeningBalanceConnectionData(
            connectionReference: $connection->reference,
            provider: $connection->provider,
            currency: $connection->currency,
            status: TreasuryOpeningBalanceStatus::Recognized,
            providerBalanceMinor: $observation->amountMinor,
            inventoryBalanceMinor: $inventoryBalanceMinor + $differenceMinor,
            positionBalanceMinor: $positionBalanceMinor + $differenceMinor,
            differenceMinor: $differenceMinor,
            evidenceReference: $observation->evidenceReference,
            observedAt: $observation->observedAt->format(DATE_ATOM),
            inventoryOperationReference: $inventoryRecognition->operationReference,
            positionOperationReference: $positionRecognition->operationReference,
        );
    }

    private function currentConnectionResult(
        TreasuryProviderConnectionData $connection,
        string $evidenceReference,
    ): TreasuryOpeningBalanceConnectionData {
        $inventory = $this->inventories->find($connection->inventoryReference);
        $inventoryBalanceMinor = $inventory?->balanceMinor ?? 0;
        $positionBalanceMinor = $this->connectionPositionBalance($connection);
        $status = $inventoryBalanceMinor === $positionBalanceMinor
            ? TreasuryOpeningBalanceStatus::Reconciled
            : TreasuryOpeningBalanceStatus::ReviewRequired;

        return new TreasuryOpeningBalanceConnectionData(
            connectionReference: $connection->reference,
            provider: $connection->provider,
            currency: $connection->currency,
            status: $status,
            providerBalanceMinor: $positionBalanceMinor,
            inventoryBalanceMinor: $inventoryBalanceMinor,
            positionBalanceMinor: $positionBalanceMinor,
            differenceMinor: 0,
            evidenceReference: $evidenceReference,
            observedAt: now()->toAtomString(),
            reason: $status === TreasuryOpeningBalanceStatus::ReviewRequired
                ? 'internal-inventory-position-mismatch'
                : null,
        );
    }

    private function connectionPositionBalance(
        TreasuryProviderConnectionData $connection,
    ): int {
        return array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            array_filter(
                $this->positions->forConnection(
                    $connection->provider,
                    $connection->reference,
                    $connection->currency,
                ),
                static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                    && $position->settlementResourceReference === $connection->settlementResourceReference,
            ),
        ));
    }

    private function registerInventory(
        TreasuryProviderConnectionData $connection,
    ): void {
        $this->inventoryRegistration->ensure(new TreasuryInventoryData(
            inventoryReference: $connection->inventoryReference,
            resourceType: $connection->settlementResourceType,
            currency: $connection->currency,
            capacityMinor: 0,
            status: 'requested',
            idempotencyKey: 'register:'.$connection->inventoryReference,
            externalReference: $connection->settlementResourceReference,
            metadata: [
                'provider' => $connection->provider,
            ],
        ));
    }

    private function assertObservationMatches(
        TreasuryProviderConnectionData $connection,
        ProviderBalanceObservationData $observation,
    ): void {
        if (
            $observation->provider !== $connection->provider
            || $observation->connectionReference !== $connection->reference
            || $observation->settlementResourceReference !== $connection->settlementResourceReference
            || $observation->currency !== $connection->currency
            || $observation->amountMinor < 0
            || trim($observation->evidenceReference) === ''
        ) {
            throw new TreasuryConfigurationException(
                "Provider balance observation did not match connection [{$connection->reference}].",
            );
        }
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function unattributedPosition(
        array $positions,
        TreasuryProviderConnectionData $connection,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose
                === TreasuryPositionPurpose::LegacyUnattributed,
        ));

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                "Connection [{$connection->reference}] requires one Legacy Unattributed Position.",
            );
        }

        return $matches[0];
    }

    private function reviewRequired(
        TreasuryProviderConnectionData $connection,
        ProviderBalanceObservationData $observation,
        int $inventoryBalanceMinor,
        int $positionBalanceMinor,
        string $reason,
    ): TreasuryOpeningBalanceConnectionData {
        return new TreasuryOpeningBalanceConnectionData(
            connectionReference: $connection->reference,
            provider: $connection->provider,
            currency: $connection->currency,
            status: TreasuryOpeningBalanceStatus::ReviewRequired,
            providerBalanceMinor: $observation->amountMinor,
            inventoryBalanceMinor: $inventoryBalanceMinor,
            positionBalanceMinor: $positionBalanceMinor,
            differenceMinor: $observation->amountMinor - $positionBalanceMinor,
            evidenceReference: $observation->evidenceReference,
            observedAt: $observation->observedAt->format(DATE_ATOM),
            reason: $reason,
        );
    }
}
