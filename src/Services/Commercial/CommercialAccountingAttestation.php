<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\PartnerCommissionPayout;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

final readonly class CommercialAccountingAttestation
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryInventoryPositionReadModelContract $inventories,
        private TreasuryPositionReadModelContract $positions,
    ) {}

    /**
     * @param  list<string>  $connectionReferences
     * @return array<string, mixed>
     */
    public function inspect(array $connectionReferences = []): array
    {
        $issues = [];
        $connections = $this->connections->active($connectionReferences);
        $connectionRows = [];

        foreach ($connections as $connection) {
            $inventory = $this->inventories->find($connection->inventoryReference);
            $positions = array_values(array_filter(
                $this->positions->forConnection(
                    $connection->provider,
                    $connection->reference,
                    $connection->currency,
                ),
                static fn (TreasuryPositionData $position): bool => $position->status === 'active',
            ));
            $positionBalanceMinor = array_sum(array_map(
                static fn (TreasuryPositionData $position): int => $position->balanceMinor,
                $positions,
            ));
            $inventoryBalanceMinor = $inventory?->balanceMinor;
            $differenceMinor = $inventoryBalanceMinor === null
                ? null
                : $inventoryBalanceMinor - $positionBalanceMinor;

            if ($inventoryBalanceMinor === null || $differenceMinor !== 0) {
                $issues[] = [
                    'code' => $inventoryBalanceMinor === null
                        ? 'inventory_not_registered'
                        : 'inventory_position_mismatch',
                    'connection_reference' => $connection->reference,
                    'expected_minor' => $inventoryBalanceMinor,
                    'actual_minor' => $positionBalanceMinor,
                ];
            }

            $connectionRows[] = [
                'reference' => $connection->reference,
                'provider' => $connection->provider,
                'currency' => $connection->currency,
                'inventory_balance_minor' => $inventoryBalanceMinor,
                'position_balance_minor' => $positionBalanceMinor,
                'difference_minor' => $differenceMinor,
            ];
        }

        $this->inspectSales($issues);
        $this->inspectCommercialPositions($connections, $issues);
        $this->inspectJournal($issues);

        return [
            'schema' => 'x-change.commercial-accounting-attestation.v1',
            'ready' => $issues === [],
            'connections' => $connectionRows,
            'commercial_sales' => CommercialSale::query()->count(),
            'provider_cost_settlements' => CommercialProviderCostSettlement::query()->count(),
            'partner_commission_payouts' => PartnerCommissionPayout::query()->count(),
            'issue_count' => count($issues),
            'issues' => $issues,
            'inspected_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function inspectSales(array &$issues): void
    {
        CommercialSale::query()
            ->with('allocations')
            ->orderBy('id')
            ->each(function (CommercialSale $sale) use (&$issues): void {
                $allocationTotalMinor = (int) $sale->allocations->sum('amount_minor');

                if ($allocationTotalMinor !== $sale->total_price_minor) {
                    $issues[] = [
                        'code' => 'allocation_total_mismatch',
                        'commercial_sale_reference' => $sale->reference,
                        'expected_minor' => $sale->total_price_minor,
                        'actual_minor' => $allocationTotalMinor,
                    ];
                }

                if ($sale->status === 'posted'
                    && $sale->allocations->contains(
                        static fn (CommercialAllocation $allocation): bool => $allocation->status !== 'posted',
                    )) {
                    $issues[] = [
                        'code' => 'allocation_status_mismatch',
                        'commercial_sale_reference' => $sale->reference,
                    ];
                }
            });

        CommercialProviderCostSettlement::query()
            ->selectRaw('commercial_allocation_id, COUNT(*) as aggregate')
            ->where('status', 'settled')
            ->groupBy('commercial_allocation_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($row) use (&$issues): void {
                $issues[] = [
                    'code' => 'duplicate_provider_cost_settlement',
                    'commercial_allocation_id' => (int) $row->commercial_allocation_id,
                ];
            });
    }

    /**
     * @param  list<mixed>  $connections
     * @param  list<array<string, mixed>>  $issues
     */
    private function inspectCommercialPositions(array $connections, array &$issues): void
    {
        $expectedByPosition = [];

        CommercialAllocation::query()
            ->where('status', 'posted')
            ->each(function (CommercialAllocation $allocation) use (&$expectedByPosition): void {
                $expected = $allocation->amount_minor;

                if ($allocation->category === 'provider_cost') {
                    $expected -= (int) CommercialProviderCostSettlement::query()
                        ->where('commercial_allocation_id', $allocation->getKey())
                        ->where('status', 'settled')
                        ->sum('observed_amount_minor');
                }

                if ($allocation->category === 'partner_commission') {
                    $expected -= (int) PartnerCommissionPayout::query()
                        ->where('commercial_allocation_id', $allocation->getKey())
                        ->where('status', 'settled')
                        ->sum('amount_minor');
                }

                $reference = $allocation->destination_position_reference;
                $expectedByPosition[$reference] = ($expectedByPosition[$reference] ?? 0) + $expected;
            });

        $commercialPurposes = [
            TreasuryPositionPurpose::CommercialClearing,
            TreasuryPositionPurpose::ProviderCostPayable,
            TreasuryPositionPurpose::ProductRevenue,
            TreasuryPositionPurpose::PartnerCommissionPayable,
            TreasuryPositionPurpose::CommercialRevenue,
        ];

        foreach ($connections as $connection) {
            foreach ($this->positions->forConnection(
                $connection->provider,
                $connection->reference,
                $connection->currency,
            ) as $position) {
                if (! in_array($position->purpose, $commercialPurposes, true)) {
                    continue;
                }

                $expected = $position->purpose === TreasuryPositionPurpose::CommercialClearing
                    ? 0
                    : (int) ($expectedByPosition[$position->positionReference] ?? 0);

                if ($position->balanceMinor !== $expected) {
                    $issues[] = [
                        'code' => $position->purpose === TreasuryPositionPurpose::CommercialClearing
                            ? 'commercial_clearing_not_zero'
                            : 'commercial_position_mismatch',
                        'position_reference' => $position->positionReference,
                        'purpose' => $position->purpose->value,
                        'expected_minor' => $expected,
                        'actual_minor' => $position->balanceMinor,
                    ];
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function inspectJournal(array &$issues): void
    {
        CommercialSale::query()
            ->withCount('allocations')
            ->whereIn('status', ['posted', 'reversed'])
            ->each(function (CommercialSale $sale) use (&$issues): void {
                $expected = 2 + $sale->allocations_count + ($sale->status === 'reversed' ? 1 : 0);
                $actual = ExecutionJournalEntry::query()
                    ->where('correlation_id', 'commercial-sale:'.$sale->reference)
                    ->whereIn('event_type', [
                        'commercial.sale.accepted',
                        'commercial.charge.posted',
                        'commercial.allocation.posted',
                        'commercial.sale.reversed',
                    ])
                    ->count();

                if ($actual !== $expected) {
                    $issues[] = [
                        'code' => 'commercial_journal_incomplete',
                        'commercial_sale_reference' => $sale->reference,
                        'expected_events' => $expected,
                        'actual_events' => $actual,
                    ];
                }
            });
    }
}
