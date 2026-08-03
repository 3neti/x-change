<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\PartnerCommissionPayout;

final readonly class CommercialSaleReversalPolicy
{
    public function assertMayReverse(
        CommercialSale $sale,
        string $reasonReference,
    ): void {
        $reasonReference = trim($reasonReference);

        if (! str_starts_with($reasonReference, 'failed-issuance:')
            && ! str_starts_with($reasonReference, 'administrative-void:')) {
            throw new CommercialSaleConflict(
                'Commercial reversals require a failed-issuance or administrative-void reason reference.',
            );
        }

        $providerCostWasSettled = CommercialProviderCostSettlement::query()
            ->where('commercial_sale_id', $sale->getKey())
            ->where('status', 'settled')
            ->exists();

        if ($providerCostWasSettled) {
            throw new CommercialSaleConflict(
                'Commercial sale cannot be reversed after provider cost settlement.',
            );
        }

        if (PartnerCommissionPayout::query()
            ->where('commercial_sale_id', $sale->getKey())
            ->exists()) {
            throw new CommercialSaleConflict(
                'Commercial sale cannot be reversed after partner payout control begins.',
            );
        }
    }
}
