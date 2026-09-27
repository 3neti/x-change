<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;

final readonly class CommercialCustomerChargingGuard
{
    public function __construct(
        private CommercialPricingScheduleInspector $pricingSchedule,
    ) {}

    public function ensureAuthorized(int $chargeMinor): void
    {
        if ($chargeMinor <= 0
            || (string) config('x-change.deployment.runtime_tier', 'production') !== 'production') {
            return;
        }

        $status = $this->pricingSchedule->inspect();

        if ($status['customer_charging_ready'] === true) {
            return;
        }

        throw new PayCodeIssuanceFailed(
            'Customer charging is not authorized until the approved pricing schedule has resolved tax treatment.',
        );
    }
}
