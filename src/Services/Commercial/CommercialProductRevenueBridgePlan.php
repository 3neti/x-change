<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;

final readonly class CommercialProductRevenueBridgePlan
{
    /** @return array{status:string, sale_reference:string, source_position_reference:string, transfers:list<array{sequence:int, recipient_reference:string, amount_minor:int, currency:string, idempotency_key:string, transfer_reference:string, reversal_reference:string, reversal_idempotency_key:string}>} */
    public function build(
        CommercialAllocationPlanData $plan,
        string $saleReference,
        string $sourcePositionReference,
    ): array {
        $saleReference = trim($saleReference);
        $sourcePositionReference = trim($sourcePositionReference);
        $transfers = [];

        foreach ($plan->lines as $line) {
            if ($line->category !== 'product_revenue' || $line->amountMinor <= 0) {
                continue;
            }

            $scope = hash('sha256', $saleReference.'|'.$line->sequence.'|'.$line->recipientReference);
            $transfers[] = [
                'sequence' => $line->sequence,
                'recipient_reference' => $line->recipientReference,
                'amount_minor' => $line->amountMinor,
                'currency' => $line->currency,
                'idempotency_key' => 'commercial-product-revenue-key:'.$scope,
                'transfer_reference' => 'commercial-product-revenue:'.$scope,
                'reversal_reference' => 'commercial-product-revenue-reversal:'.$scope,
                'reversal_idempotency_key' => 'commercial-product-revenue-reversal-key:'.$scope,
            ];
        }

        return [
            'status' => 'planned',
            'sale_reference' => $saleReference,
            'source_position_reference' => $sourcePositionReference,
            'transfers' => $transfers,
        ];
    }
}
