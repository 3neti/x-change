<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class AssignedBankTreasuryConnection
{
    public function __construct(private TreasuryProviderConnectionCatalog $catalog) {}

    public function bind(Model $owner, int $assignmentId, string $reference): TreasuryProviderConnectionData
    {
        return DB::transaction(function () use ($owner, $assignmentId, $reference): TreasuryProviderConnectionData {
            $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
                ->lockForUpdate()->findOrFail($assignmentId);
            $existing = $assignment->treasury_connection_reference;
            if ($existing !== null && $existing !== $reference) {
                throw new RuntimeException('A bank account Treasury connection cannot be changed.');
            }
            $connection = $this->validate($assignment, $reference);
            $assignment->forceFill([
                'treasury_connection_reference' => $reference,
                'treasury_inventory_reference' => $connection->inventoryReference,
                'treasury_resource_reference' => $connection->settlementResourceReference,
            ])->save();

            return $connection;
        }, attempts: 3);
    }

    public function resolve(Model $owner, int $accountLinkId): TreasuryProviderConnectionData
    {
        $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
            ->where('provider_account_link_id', $accountLinkId)->first();
        if ($assignment === null || ! is_string($assignment->treasury_connection_reference)) {
            throw new RuntimeException('This bank account does not have a Treasury connection.');
        }

        return $this->validate($assignment, $assignment->treasury_connection_reference);
    }

    private function validate(BankAccountAssignment $assignment, string $reference): TreasuryProviderConnectionData
    {
        $all = $this->catalog->all();
        $matches = array_values(array_filter($all, fn (TreasuryProviderConnectionData $item): bool => $item->reference === $reference));
        $connection = $matches[0] ?? null;
        if (count($matches) !== 1 || ! $connection->isActive() || $connection->provider !== $assignment->provider || $connection->currency !== 'PHP') {
            throw new RuntimeException('The bank account Treasury connection is unavailable.');
        }
        if ($assignment->treasury_connection_reference !== null && (
            $assignment->treasury_inventory_reference !== $connection->inventoryReference
            || $assignment->treasury_resource_reference !== $connection->settlementResourceReference
        )) {
            throw new RuntimeException('The bank account Treasury connection no longer matches its assigned resources.');
        }
        $shared = config("x-change.funding.providers.{$assignment->provider}.treasury", []);
        if (($shared['inventory_reference'] ?? null) === $connection->inventoryReference
            || ($shared['settlement_resource_reference'] ?? null) === $connection->settlementResourceReference) {
            throw new RuntimeException('The bank account Treasury connection must use a separate inventory and settlement resource.');
        }
        foreach ($all as $other) {
            if ($other->reference !== $connection->reference && ($other->inventoryReference === $connection->inventoryReference
                || $other->settlementResourceReference === $connection->settlementResourceReference)) {
                throw new RuntimeException('The bank account Treasury connection must use a separate inventory and settlement resource.');
            }
        }
        if (BankAccountAssignment::query()->where('treasury_connection_reference', $reference)
            ->whereKeyNot($assignment->getKey())->exists()) {
            throw new RuntimeException('The bank account Treasury connection is already assigned.');
        }

        return $connection;
    }
}
