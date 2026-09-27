<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\CommercialBillingMode;

final class CommercialBillingPolicy
{
    public function mode(): CommercialBillingMode
    {
        return CommercialBillingMode::tryFrom((string) config(
            'x-change.commercial.billing.mode',
            CommercialBillingMode::Informational->value,
        )) ?? CommercialBillingMode::Informational;
    }

    public function isBillable(): bool
    {
        return $this->mode() === CommercialBillingMode::Billable;
    }

    public function customerChargeMinor(PricingEstimateData $estimate): int
    {
        return $this->isBillable()
            ? (int) round($estimate->total * 100)
            : 0;
    }

    public function accountDebit(float $principal, float $estimatedCharge, bool $collectsFunds): float
    {
        $fundedPrincipal = $collectsFunds ? 0.0 : $principal;
        $customerCharge = $this->isBillable() ? $estimatedCharge : 0.0;

        return round($fundedPrincipal + $customerCharge, 2);
    }
}
