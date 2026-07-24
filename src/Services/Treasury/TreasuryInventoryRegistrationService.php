<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryPositionData;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryOperationConflict;

final readonly class TreasuryInventoryRegistrationService
{
    public function __construct(
        private TreasuryInventoryOperationContract $operations,
        private TreasuryInventoryPositionReadModelContract $inventories,
    ) {}

    public function ensure(
        TreasuryInventoryData $registration,
    ): TreasuryInventoryPositionData {
        $existing = $this->inventories->find(
            $registration->inventoryReference,
        );

        if ($existing instanceof TreasuryInventoryPositionData) {
            $this->assertMatches($existing, $registration);

            return $existing;
        }

        $this->operations->registerInventory($registration);
        $registered = $this->inventories->find(
            $registration->inventoryReference,
        );

        if (! $registered instanceof TreasuryInventoryPositionData) {
            throw new TreasuryOperationConflict(
                'Treasury Inventory registration did not become readable.',
            );
        }

        $this->assertMatches($registered, $registration);

        return $registered;
    }

    private function assertMatches(
        TreasuryInventoryPositionData $existing,
        TreasuryInventoryData $registration,
    ): void {
        if (
            $existing->settlementResourceReference
                !== $registration->externalReference
            || $existing->resourceType !== $registration->resourceType
            || $existing->currency !== $registration->currency
            || $existing->status !== 'active'
        ) {
            throw new TreasuryOperationConflict(
                'Treasury Inventory definition conflicts with its existing registration.',
            );
        }
    }
}
