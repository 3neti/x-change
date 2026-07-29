<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Support\Facades\Schema;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Data\Treasury\TreasuryInitializationStateData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;

final readonly class TreasuryInitializationStateService
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasurySystemPositionCatalog $systemPositionCatalog,
        private TreasuryInventoryPositionReadModelContract $inventories,
        private TreasuryPositionReadModelContract $positions,
    ) {}

    public function inspect(): TreasuryInitializationStateData
    {
        $initialized = [];
        $uninitialized = [];
        $incomplete = [];
        $hasInventoryTable = Schema::hasTable('treasury_inventories');
        $hasPositionTable = Schema::hasTable('treasury_positions');

        foreach ($this->connections->active() as $connection) {
            $inventory = $hasInventoryTable
                ? $this->inventories->find($connection->inventoryReference)
                : null;
            [$positionCount, $positionConflict] = $hasPositionTable
                ? $this->systemPositionState($connection)
                : [0, false];

            if (
                $positionConflict
                || (
                    $inventory instanceof TreasuryInventoryPositionData
                    && ! $this->inventoryMatches($inventory, $connection)
                )
            ) {
                $incomplete[] = $connection->reference;

                continue;
            }

            if (
                $this->inventoryMatches($inventory, $connection)
                && $positionCount
                    === count($this->systemPositionCatalog->all())
            ) {
                $initialized[] = $connection->reference;

                continue;
            }

            if ($inventory === null) {
                $uninitialized[] = $connection->reference;

                continue;
            }

            $incomplete[] = $connection->reference;
        }

        return new TreasuryInitializationStateData(
            initialized: $initialized,
            uninitialized: $uninitialized,
            incomplete: $incomplete,
        );
    }

    /**
     * @return array{int, bool}
     */
    private function systemPositionState(
        TreasuryProviderConnectionData $connection,
    ): array {
        $positionCount = 0;
        $conflict = false;

        foreach ($this->systemPositionCatalog->all() as $suffix => $purpose) {
            $reference = implode(':', [
                'position',
                'system',
                $connection->provider,
                $connection->reference,
                mb_strtolower($connection->currency),
                $suffix,
            ]);
            $position = $this->positions->find($reference);

            if ($position === null) {
                continue;
            }

            if (! $this->positionMatches($position, $connection, $purpose)) {
                $conflict = true;

                continue;
            }

            $positionCount++;
        }

        return [$positionCount, $conflict];
    }

    private function inventoryMatches(
        ?TreasuryInventoryPositionData $inventory,
        TreasuryProviderConnectionData $connection,
    ): bool {
        return $inventory instanceof TreasuryInventoryPositionData
            && $inventory->settlementResourceReference
                === $connection->settlementResourceReference
            && $inventory->resourceType === $connection->settlementResourceType
            && $inventory->currency === $connection->currency
            && $inventory->status === 'active';
    }

    private function positionMatches(
        ?TreasuryPositionData $position,
        TreasuryProviderConnectionData $connection,
        TreasuryPositionPurpose $purpose,
    ): bool {
        return $position instanceof TreasuryPositionData
            && $position->principalReference
                === trim((string) config('x-change.treasury.principal_reference'))
            && $position->mandateReference
                === trim((string) config('x-change.treasury.system_mandate_reference'))
            && $position->provider === $connection->provider
            && $position->connectionReference === $connection->reference
            && $position->currency === $connection->currency
            && $position->decimalPlaces === $connection->decimalPlaces
            && $position->settlementResourceReference
                === $connection->settlementResourceReference
            && $position->purpose === $purpose
            && $position->custodyMode === $connection->custodyMode
            && $position->legalProfile
                === trim((string) config('x-change.treasury.legal_profile'))
            && $position->legalProfileVersion
                === trim((string) config('x-change.treasury.legal_profile_version'))
            && $position->reconciliationReference
                === "reconciliation:{$connection->provider}:{$connection->reference}"
            && $position->status === 'active';
    }
}
