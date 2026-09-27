<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\CommercialBillingMode;
use LBHurtado\XChange\Services\Commercial\CommercialBillingPolicy;

it('keeps estimates visible without debiting service charges in informational mode', function (): void {
    config()->set('x-change.commercial.billing.mode', 'informational');

    $policy = app(CommercialBillingPolicy::class);
    $estimate = new PricingEstimateData(
        currency: 'PHP',
        total: 25.0,
        pay_code_value: 100.0,
    );

    expect($policy->mode())->toBe(CommercialBillingMode::Informational)
        ->and($policy->customerChargeMinor($estimate))->toBe(0)
        ->and($policy->accountDebit(100.0, 25.0, false))->toBe(100.0)
        ->and($policy->accountDebit(100.0, 25.0, true))->toBe(0.0);
});

it('includes approved service charges only in billable mode', function (): void {
    config()->set('x-change.commercial.billing.mode', 'billable');

    $policy = app(CommercialBillingPolicy::class);
    $estimate = new PricingEstimateData(
        currency: 'PHP',
        total: 25.0,
        pay_code_value: 100.0,
    );

    expect($policy->mode())->toBe(CommercialBillingMode::Billable)
        ->and($policy->customerChargeMinor($estimate))->toBe(2_500)
        ->and($policy->accountDebit(100.0, 25.0, false))->toBe(125.0)
        ->and($policy->accountDebit(100.0, 25.0, true))->toBe(25.0);
});
