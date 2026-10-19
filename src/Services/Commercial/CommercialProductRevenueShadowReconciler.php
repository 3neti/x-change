<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;

final readonly class CommercialProductRevenueShadowReconciler
{
    /**
     * @param  iterable<array{category:string, amount_minor:int, recipient_reference:string, status:string}>  $postedAllocations
     * @return array{status:string, expected_product_revenue_minor:int, actual_product_revenue_minor:int, variance_minor:int, checks:array{allocation_statuses_posted:bool, product_amounts_match:bool, recipient_references_match:bool}}
     */
    public function reconcile(
        CommercialAllocationPlanData $plan,
        iterable $postedAllocations,
    ): array {
        $expected = collect($plan->lines)
            ->where('category', 'product_revenue')
            ->values();
        $actual = collect($postedAllocations)
            ->filter(static fn (array $allocation): bool => $allocation['category'] === 'product_revenue')
            ->values();
        $expectedRecipients = $expected->pluck('recipientReference')->sort()->values()->all();
        $actualRecipients = $actual->pluck('recipient_reference')->sort()->values()->all();
        $checks = [
            'allocation_statuses_posted' => collect($postedAllocations)
                ->every(static fn (array $allocation): bool => $allocation['status'] === 'posted'),
            'product_amounts_match' => (int) $expected->sum('amountMinor') === (int) $actual->sum('amount_minor'),
            'recipient_references_match' => $expectedRecipients === $actualRecipients,
        ];
        $expectedMinor = (int) $expected->sum('amountMinor');
        $actualMinor = (int) $actual->sum('amount_minor');

        return [
            'status' => collect($checks)->every(static fn (bool $check): bool => $check)
                ? 'reconciled'
                : 'attention',
            'expected_product_revenue_minor' => $expectedMinor,
            'actual_product_revenue_minor' => $actualMinor,
            'variance_minor' => $actualMinor - $expectedMinor,
            'checks' => $checks,
        ];
    }
}
