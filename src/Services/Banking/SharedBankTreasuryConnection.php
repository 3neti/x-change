<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class SharedBankTreasuryConnection
{
    public function __construct(private TreasuryProviderConnectionCatalog $catalog) {}

    public function resolveIfAccountsAssigned(string $provider, string $currency): ?TreasuryProviderConnectionData
    {
        if ($provider !== 'netbank' || ! BankAccountAssignment::query()->where('provider', $provider)
            ->whereNotNull('treasury_connection_reference')->exists()) {
            return null;
        }

        $shared = config("x-change.funding.providers.{$provider}.treasury", []);
        $inventory = $shared['inventory_reference'] ?? null;
        $resource = $shared['settlement_resource_reference'] ?? null;
        $matches = array_values(array_filter($this->catalog->active(),
            static fn (TreasuryProviderConnectionData $connection): bool => $connection->provider === $provider
                && $connection->currency === $currency && $connection->inventoryReference === $inventory
                && $connection->settlementResourceReference === $resource));
        if (count($matches) !== 1 || BankAccountAssignment::query()
            ->where('treasury_connection_reference', $matches[0]->reference)->exists()) {
            throw new RuntimeException('The shared bank account Treasury connection is unavailable.');
        }

        return $matches[0];
    }
}
