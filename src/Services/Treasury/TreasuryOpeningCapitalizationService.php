<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Support\Facades\Cache;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryOpeningCapitalizationAuthorizationContract;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningCapitalizationAuthorizationData;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningCapitalizationConnectionData;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningCapitalizationData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\TreasuryOpeningBalanceStatus;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final readonly class TreasuryOpeningCapitalizationService
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryProvisioningService $provisioning,
        private TreasuryOpeningBalanceReconciliationService $reconciliation,
        private TreasuryInventoryPositionReadModelContract $inventories,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryPositionOperationContract $operations,
        private TreasuryOpeningCapitalizationAuthorizationContract $authorization,
    ) {}

    /**
     * @param  list<string>  $connectionReferences
     */
    public function capitalize(
        array $connectionReferences,
        string $authorizationReference,
        bool $systemOwnershipConfirmed,
        bool $commit,
    ): TreasuryOpeningCapitalizationData {
        $selected = $this->connections->active($connectionReferences);
        $this->provisioning->provision(array_map(
            static fn (TreasuryProviderConnectionData $connection): string => $connection->reference,
            $selected,
        ));
        $results = [];

        foreach ($selected as $connection) {
            $lock = Cache::lock(
                'x-change:treasury:opening-capitalization:'.hash(
                    'sha256',
                    $connection->reference,
                ),
                max(
                    1,
                    (int) config(
                        'x-change.treasury.opening_capitalization.lock_seconds',
                        60,
                    ),
                ),
            );
            $results[] = $lock->block(
                max(
                    0,
                    (int) config(
                        'x-change.treasury.opening_capitalization.lock_wait_seconds',
                        5,
                    ),
                ),
                fn (): TreasuryOpeningCapitalizationConnectionData => $this->capitalizeConnection(
                    $connection,
                    $authorizationReference,
                    $systemOwnershipConfirmed,
                    $commit,
                ),
            );
        }

        return new TreasuryOpeningCapitalizationData($results);
    }

    private function capitalizeConnection(
        TreasuryProviderConnectionData $connection,
        string $authorizationReference,
        bool $systemOwnershipConfirmed,
        bool $commit,
    ): TreasuryOpeningCapitalizationConnectionData {
        $observation = collect(
            $this->reconciliation
                ->observe([$connection->reference])
                ->connections,
        )->sole();

        if (
            $observation->status !== TreasuryOpeningBalanceStatus::Reconciled
            || $observation->differenceMinor !== 0
            || $observation->providerBalanceMinor
                !== $observation->inventoryBalanceMinor
            || $observation->inventoryBalanceMinor
                !== $observation->positionBalanceMinor
        ) {
            throw new TreasuryConfigurationException(
                "Connection [{$connection->reference}] must be authoritatively reconciled before opening capitalization.",
            );
        }

        $positions = $this->connectionPositions($connection);
        $legacy = $this->position(
            $positions,
            TreasuryPositionPurpose::LegacyUnattributed,
        );
        $reserve = $this->position(
            $positions,
            TreasuryPositionPurpose::AccountFundingReserve,
        );
        $operationReference = $this->operationReference($connection);

        if ($this->positions->operationExists($operationReference)) {
            return $this->result(
                connection: $connection,
                status: 'already_capitalized',
                providerBalanceMinor: $observation->providerBalanceMinor,
                inventoryBalanceMinor: $observation->inventoryBalanceMinor,
                positionBalanceMinor: $observation->positionBalanceMinor,
                amountMinor: 0,
                legacyBeforeMinor: $legacy->balanceMinor,
                legacyAfterMinor: $legacy->balanceMinor,
                reserveBeforeMinor: $reserve->balanceMinor,
                reserveAfterMinor: $reserve->balanceMinor,
                operationReference: $operationReference,
            );
        }

        if ($reserve->balanceMinor !== 0) {
            throw new TreasuryConfigurationException(
                "Connection [{$connection->reference}] has an unrecognized Account Funding Reserve opening balance.",
            );
        }

        if ($legacy->balanceMinor === 0) {
            return $this->result(
                connection: $connection,
                status: 'no_unattributed_funds',
                providerBalanceMinor: $observation->providerBalanceMinor,
                inventoryBalanceMinor: $observation->inventoryBalanceMinor,
                positionBalanceMinor: $observation->positionBalanceMinor,
                amountMinor: 0,
                legacyBeforeMinor: 0,
                legacyAfterMinor: 0,
                reserveBeforeMinor: 0,
                reserveAfterMinor: 0,
            );
        }

        $this->authorization->authorize(
            new TreasuryOpeningCapitalizationAuthorizationData(
                connectionReference: $connection->reference,
                amountMinor: $legacy->balanceMinor,
                currency: $connection->currency,
                authorizationReference: $authorizationReference,
                systemOwnershipConfirmed: $systemOwnershipConfirmed,
                commit: $commit,
            ),
        );

        if (! $commit) {
            return $this->result(
                connection: $connection,
                status: 'preview_ready',
                providerBalanceMinor: $observation->providerBalanceMinor,
                inventoryBalanceMinor: $observation->inventoryBalanceMinor,
                positionBalanceMinor: $observation->positionBalanceMinor,
                amountMinor: $legacy->balanceMinor,
                legacyBeforeMinor: $legacy->balanceMinor,
                legacyAfterMinor: 0,
                reserveBeforeMinor: 0,
                reserveAfterMinor: $legacy->balanceMinor,
                operationReference: $operationReference,
            );
        }

        $allocation = $this->operations->allocate(
            new TreasuryPositionAllocationData(
                operationReference: $operationReference,
                sourcePositionReference: $legacy->positionReference,
                destinationPositionReference: $reserve->positionReference,
                amountMinor: $legacy->balanceMinor,
                currency: $connection->currency,
                idempotencyKey: $operationReference.':key',
                externalReference: trim($authorizationReference),
                metadata: [
                    'source' => 'x_change_treasury_opening_capitalization',
                    'provider' => $connection->provider,
                    'connection_reference' => $connection->reference,
                    'provider_evidence_hash' => hash(
                        'sha256',
                        $observation->evidenceReference,
                    ),
                    'system_ownership_confirmed' => true,
                ],
            ),
        );
        $after = $this->connectionPositions($connection);
        $legacyAfter = $this->position(
            $after,
            TreasuryPositionPurpose::LegacyUnattributed,
        );
        $reserveAfter = $this->position(
            $after,
            TreasuryPositionPurpose::AccountFundingReserve,
        );
        $inventoryAfter = $this->inventories->find(
            $connection->inventoryReference,
        );
        $positionBalanceAfter = array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            $after,
        ));

        if (
            $inventoryAfter === null
            || $inventoryAfter->balanceMinor !== $observation->inventoryBalanceMinor
            || $positionBalanceAfter !== $inventoryAfter->balanceMinor
            || $legacyAfter->balanceMinor !== 0
            || $reserveAfter->balanceMinor !== $legacy->balanceMinor
        ) {
            throw new TreasuryConfigurationException(
                "Connection [{$connection->reference}] failed its post-capitalization invariants.",
            );
        }

        return $this->result(
            connection: $connection,
            status: 'capitalized',
            providerBalanceMinor: $observation->providerBalanceMinor,
            inventoryBalanceMinor: $inventoryAfter->balanceMinor,
            positionBalanceMinor: $positionBalanceAfter,
            amountMinor: $allocation->amountMinor,
            legacyBeforeMinor: $legacy->balanceMinor,
            legacyAfterMinor: $legacyAfter->balanceMinor,
            reserveBeforeMinor: $reserve->balanceMinor,
            reserveAfterMinor: $reserveAfter->balanceMinor,
            operationReference: $allocation->operationReference,
        );
    }

    /**
     * @return list<TreasuryPositionData>
     */
    private function connectionPositions(
        TreasuryProviderConnectionData $connection,
    ): array {
        return array_values(array_filter(
            $this->positions->forConnection(
                $connection->provider,
                $connection->reference,
                $connection->currency,
            ),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->settlementResourceReference
                    === $connection->settlementResourceReference,
        ));
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
            static fn (TreasuryPositionData $position): bool => $position->purpose
                === $purpose,
        ));

        if (count($matches) !== 1) {
            throw new TreasuryConfigurationException(
                "Treasury opening capitalization requires one {$purpose->value} Position.",
            );
        }

        return $matches[0];
    }

    private function operationReference(
        TreasuryProviderConnectionData $connection,
    ): string {
        return 'opening-system-capitalization:'.hash('sha256', implode('|', [
            trim((string) config(
                'x-change.treasury.legal_entity_reference',
            )),
            $connection->provider,
            $connection->reference,
            $connection->currency,
        ]));
    }

    private function result(
        TreasuryProviderConnectionData $connection,
        string $status,
        int $providerBalanceMinor,
        int $inventoryBalanceMinor,
        int $positionBalanceMinor,
        int $amountMinor,
        int $legacyBeforeMinor,
        int $legacyAfterMinor,
        int $reserveBeforeMinor,
        int $reserveAfterMinor,
        ?string $operationReference = null,
    ): TreasuryOpeningCapitalizationConnectionData {
        return new TreasuryOpeningCapitalizationConnectionData(
            connectionReference: $connection->reference,
            provider: $connection->provider,
            currency: $connection->currency,
            status: $status,
            providerBalanceMinor: $providerBalanceMinor,
            inventoryBalanceMinor: $inventoryBalanceMinor,
            positionBalanceMinor: $positionBalanceMinor,
            capitalizedAmountMinor: $amountMinor,
            legacyUnattributedBeforeMinor: $legacyBeforeMinor,
            legacyUnattributedAfterMinor: $legacyAfterMinor,
            accountFundingReserveBeforeMinor: $reserveBeforeMinor,
            accountFundingReserveAfterMinor: $reserveAfterMinor,
            operationReference: $operationReference,
        );
    }
}
