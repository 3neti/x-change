<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\PartnerCommissionPayout;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

final class CommercialControlReadModel
{
    /**
     * @return array<string, mixed>
     */
    public function build(CommercialOfferingData $offering): array
    {
        $allocationTotals = CommercialAllocation::query()
            ->selectRaw('category, currency, SUM(amount_minor) as amount_minor, COUNT(*) as allocation_count')
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
            'provider_costs' => [
                'settled_count' => CommercialProviderCostSettlement::query()
                    ->where('status', 'settled')
                    ->count(),
                'settled_minor' => (int) CommercialProviderCostSettlement::query()
                    ->where('status', 'settled')
                    ->sum('observed_amount_minor'),
                'variance_minor' => (int) CommercialProviderCostSettlement::query()
                    ->sum('variance_amount_minor'),
            ],
            'commissions' => [
                'earned_minor' => (int) CommercialAllocation::query()
                    ->where('category', 'partner_commission')
                    ->where('status', 'posted')
                    ->sum('amount_minor'),
                'requested_minor' => (int) PartnerCommissionPayout::query()
                    ->whereIn('status', ['requested', 'approved'])
                    ->sum('amount_minor'),
                'settled_minor' => (int) PartnerCommissionPayout::query()
                    ->where('status', 'settled')
                    ->sum('amount_minor'),
                'open_count' => PartnerCommissionPayout::query()
                    ->whereIn('status', ['requested', 'approved'])
                    ->count(),
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
}
