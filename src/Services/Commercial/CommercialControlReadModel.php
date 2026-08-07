<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialProviderCostBatch;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\PartnerCommissionPayout;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

final readonly class CommercialControlReadModel
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryPositionReadModelContract $positions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CommercialOfferingData $offering): array
    {
        $allocationTotals = CommercialAllocation::query()
            ->selectRaw('category, currency, SUM(amount_minor) as amount_minor, COUNT(*) as allocation_count')
            ->where('status', 'posted')
            ->groupBy('category', 'currency')
            ->orderBy('category')
            ->get()
            ->map(fn (CommercialAllocation $allocation): array => [
                'category' => $allocation->category,
                'currency' => $allocation->currency,
                'amount_minor' => (int) $allocation->getAttribute('amount_minor'),
                'allocation_count' => (int) $allocation->getAttribute('allocation_count'),
            ])
            ->values()
            ->all();

        return [
            'schema' => 'x-change.cockpit.commercial-controls.v1',
            'sales' => [
                'count' => CommercialSale::query()->count(),
                'posted_count' => CommercialSale::query()->where('status', 'posted')->count(),
                'reversed_count' => CommercialSale::query()->where('status', 'reversed')->count(),
                'total_charged_minor' => (int) CommercialSale::query()
                    ->where('status', 'posted')
                    ->sum('total_price_minor'),
                'currency' => $offering->catalog->currency,
            ],
            'allocation_totals' => $allocationTotals,
            'position_balances' => $this->positionBalances($allocationTotals),
            'provider_costs' => [
                'settled_count' => CommercialProviderCostSettlement::query()
                    ->where('status', 'settled')
                    ->count(),
                'settled_minor' => (int) CommercialProviderCostSettlement::query()
                    ->where('status', 'settled')
                    ->sum('observed_amount_minor'),
                'variance_minor' => (int) CommercialProviderCostSettlement::query()
                    ->sum('variance_amount_minor'),
                'outstanding_minor' => max(0, (int) CommercialAllocation::query()
                    ->where('category', 'provider_cost')
                    ->where('status', 'posted')
                    ->sum('amount_minor') - (int) CommercialProviderCostSettlement::query()
                    ->where('status', 'settled')
                    ->sum('observed_amount_minor')),
                'recent_batches' => CommercialProviderCostBatch::query()
                    ->latest('observed_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (CommercialProviderCostBatch $batch): array => [
                        'reference' => $batch->reference,
                        'provider' => $batch->provider,
                        'connection_reference' => $batch->connection_reference,
                        'currency' => $batch->currency,
                        'expected_amount_minor' => $batch->expected_amount_minor,
                        'observed_amount_minor' => $batch->observed_amount_minor,
                        'variance_amount_minor' => $batch->variance_amount_minor,
                        'status' => $batch->status->value,
                        'observed_at' => $batch->observed_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            'commissions' => [
                'earned_minor' => (int) CommercialAllocation::query()
                    ->where('category', 'partner_commission')
                    ->where('status', 'posted')
                    ->sum('amount_minor'),
                'requested_minor' => (int) PartnerCommissionPayout::query()
                    ->whereIn('status', ['requested', 'approved'])
                    ->sum('amount_minor') + (int) PartnerCommissionPayoutBatch::query()
                    ->whereIn('status', ['awaiting_approval', 'approved', 'submitted', 'pending'])
                    ->sum('amount_minor'),
                'settled_minor' => (int) PartnerCommissionPayout::query()
                    ->where('status', 'settled')
                    ->sum('amount_minor') + (int) PartnerCommissionPayoutBatch::query()
                    ->where('status', 'settled')
                    ->sum('amount_minor'),
                'open_count' => PartnerCommissionPayout::query()
                    ->whereIn('status', ['requested', 'approved'])
                    ->count() + PartnerCommissionPayoutBatch::query()
                    ->whereIn('status', ['awaiting_approval', 'approved', 'submitted', 'pending', 'suspense'])
                    ->count(),
                'available_minor' => max(0, (int) CommercialAllocation::query()
                    ->where('category', 'partner_commission')
                    ->where('status', 'posted')
                    ->sum('amount_minor') - (int) PartnerCommissionPayoutBatch::query()
                    ->whereIn('status', ['awaiting_approval', 'approved', 'submitted', 'pending', 'settled', 'suspense'])
                    ->sum('amount_minor') - (int) PartnerCommissionPayout::query()->sum('amount_minor')),
                'recent_batches' => PartnerCommissionPayoutBatch::query()
                    ->latest('requested_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (PartnerCommissionPayoutBatch $batch): array => [
                        'reference' => $batch->reference,
                        'partner_reference' => $batch->partner_reference,
                        'provider' => $batch->provider,
                        'connection_reference' => $batch->connection_reference,
                        'destination_summary' => $batch->destination_summary,
                        'amount_minor' => $batch->amount_minor,
                        'currency' => $batch->currency,
                        'status' => $batch->status->value,
                        'requested_at' => $batch->requested_at?->toIso8601String(),
                        'settled_at' => $batch->settled_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            'recent_sales' => CommercialSale::query()
                ->with('allocations')
                ->latest('accepted_at')
                ->limit(10)
                ->get()
                ->map(fn (CommercialSale $sale): array => [
                    'reference' => $sale->reference,
                    'buyer_reference' => $sale->buyer_reference,
                    'amount_minor' => $sale->total_price_minor,
                    'currency' => $sale->currency,
                    'status' => $sale->status,
                    'accepted_at' => $sale->accepted_at?->toIso8601String(),
                    'allocations' => $sale->allocations
                        ->map(fn (CommercialAllocation $allocation): array => [
                            'category' => $allocation->category,
                            'recipient_reference' => $allocation->recipient_reference,
                            'amount_minor' => $allocation->amount_minor,
                            'status' => $allocation->status,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'policy' => [
                'attribution' => $offering->attributionPolicy->toArray(),
                'legal_trace' => $offering->legalTrace->toArray(),
                'commercial_terms_are_not_client_funds' => true,
                'commission_requires_attributed_participant' => true,
                'provider_cost_requires_authoritative_evidence' => true,
            ],
        ];
    }

    /**
     * @param  list<array{category: string, currency: string, amount_minor: int, allocation_count: int}>  $allocationTotals
     * @return list<array<string, int|string|bool>>
     */
    private function positionBalances(array $allocationTotals): array
    {
        $purposes = [
            TreasuryPositionPurpose::ProviderCostPayable->value => 'provider_cost',
            TreasuryPositionPurpose::ProductRevenue->value => 'product_revenue',
            TreasuryPositionPurpose::PartnerCommissionPayable->value => 'partner_commission',
            TreasuryPositionPurpose::CommercialRevenue->value => 'commercial_revenue',
        ];
        $current = [];

        foreach ($this->connections->active() as $connection) {
            foreach ($this->positions->forConnection(
                $connection->provider,
                $connection->reference,
                $connection->currency,
            ) as $position) {
                $purpose = $position->purpose->value;

                if ($position->status !== 'active' || ! isset($purposes[$purpose])) {
                    continue;
                }

                $key = $purpose.'|'.$position->currency;
                $current[$key] = ($current[$key] ?? 0) + $position->balanceMinor;
            }
        }

        $settled = CommercialProviderCostSettlement::query()
            ->selectRaw('currency, SUM(observed_amount_minor) as amount_minor')
            ->where('status', 'settled')
            ->groupBy('currency')
            ->pluck('amount_minor', 'currency')
            ->mapWithKeys(fn (mixed $amount, string $currency): array => [
                'provider_cost|'.$currency => (int) $amount,
            ]);
        $legacyCommissionSettled = PartnerCommissionPayout::query()
            ->selectRaw('currency, SUM(amount_minor) as amount_minor')
            ->where('status', 'settled')
            ->groupBy('currency')
            ->pluck('amount_minor', 'currency');
        $batchCommissionSettled = PartnerCommissionPayoutBatch::query()
            ->selectRaw('currency, SUM(amount_minor) as amount_minor')
            ->where('status', 'settled')
            ->groupBy('currency')
            ->pluck('amount_minor', 'currency');

        foreach ($legacyCommissionSettled->keys()->merge($batchCommissionSettled->keys())->unique() as $currency) {
            $settled->put(
                'partner_commission|'.$currency,
                (int) $legacyCommissionSettled->get($currency, 0)
                    + (int) $batchCommissionSettled->get($currency, 0),
            );
        }
        $rows = [];

        foreach ($purposes as $purpose => $category) {
            $totals = collect($allocationTotals)
                ->where('category', $category)
                ->keyBy('currency');
            $currencies = collect(array_keys($current))
                ->filter(fn (string $key): bool => str_starts_with($key, $purpose.'|'))
                ->map(fn (string $key): string => str($key)->after('|')->toString())
                ->merge($totals->keys())
                ->unique()
                ->sort()
                ->values();

            foreach ($currencies as $currency) {
                $lifetimeAllocatedMinor = (int) ($totals->get($currency)['amount_minor'] ?? 0);
                $settledMinor = (int) $settled->get($category.'|'.$currency, 0);
                $currentMinor = (int) ($current[$purpose.'|'.$currency] ?? 0);
                $expectedRemainingMinor = $lifetimeAllocatedMinor - $settledMinor;

                $rows[] = [
                    'purpose' => $purpose,
                    'category' => $category,
                    'currency' => $currency,
                    'current_minor' => $currentMinor,
                    'lifetime_allocated_minor' => $lifetimeAllocatedMinor,
                    'settled_minor' => $settledMinor,
                    'remaining_minor' => $currentMinor,
                    'difference_minor' => $currentMinor - $expectedRemainingMinor,
                    'reconciled' => $currentMinor === $expectedRemainingMinor,
                ];
            }
        }

        return $rows;
    }
}
