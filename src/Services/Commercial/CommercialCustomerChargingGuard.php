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

        if ($this->betaChargingExceptionIsAuthorized($status)) {
            return;
        }

        throw new PayCodeIssuanceFailed(
            'Customer charging is not authorized until the approved pricing schedule has resolved tax and invoicing authority.',
        );
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function betaChargingExceptionIsAuthorized(array $status): bool
    {
        return config('x-change.commercial.beta_customer_charging_exception.enabled') === true
            && filled(config(
                'x-change.commercial.beta_customer_charging_exception.authorization_reference',
            ))
            && ($status['schedule_ready'] ?? false) === true
            && data_get($status, 'principal.excluded_from_charges_and_revenue') === true
            && data_get($status, 'customer_authorization.explicit') === true
            && data_get($status, 'receipt_reporting.verified') === true;
    }
}
