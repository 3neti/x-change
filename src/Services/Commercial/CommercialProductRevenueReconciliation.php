<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;

final readonly class CommercialProductRevenueReconciliation
{
    /**
     * @return array{
     *     status:string,
     *     currency:string,
     *     quote_total_minor:int,
     *     allocated_total_minor:int,
     *     product_revenue_minor:int,
     *     principal_minor:int,
     *     non_product_minor:int,
     *     product_lines:list<array{sequence:int, recipient_reference:string, amount_minor:int, currency:string}>,
     *     checks:array{quote_conserved:bool, product_lines_governed:bool, principal_separate:bool}
     * }
     */
    public function reconcile(CommercialAllocationPlanData $plan): array
    {
        $productLines = [];
        $productRevenueMinor = 0;
        $principalMinor = 0;

        foreach ($plan->lines as $line) {
            if ($line->category === 'product_revenue') {
                $productRevenueMinor += $line->amountMinor;
                $productLines[] = [
                    'sequence' => $line->sequence,
                    'recipient_reference' => $line->recipientReference,
                    'amount_minor' => $line->amountMinor,
                    'currency' => $line->currency,
                ];
            }

            if ($line->category === 'principal') {
                $principalMinor += $line->amountMinor;
            }
        }

        $allocatedTotalMinor = $plan->totalAllocatedMinor();
        $nonProductMinor = $allocatedTotalMinor - $productRevenueMinor;
        $checks = [
            'quote_conserved' => $allocatedTotalMinor === $plan->allocationBaseMinor,
            'product_lines_governed' => collect($productLines)
                ->every(static fn (array $line): bool => $line['amount_minor'] > 0
                    && $line['recipient_reference'] !== ''),
            'principal_separate' => $principalMinor === 0,
        ];

        return [
            'status' => collect($checks)->every(static fn (bool $check): bool => $check)
                ? 'reconciled'
                : 'attention',
            'currency' => $plan->currency,
            'quote_total_minor' => $plan->allocationBaseMinor,
            'allocated_total_minor' => $allocatedTotalMinor,
            'product_revenue_minor' => $productRevenueMinor,
            'principal_minor' => $principalMinor,
            'non_product_minor' => $nonProductMinor,
            'product_lines' => $productLines,
            'checks' => $checks,
        ];
    }
}
