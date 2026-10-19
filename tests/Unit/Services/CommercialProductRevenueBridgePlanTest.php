<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Commercial\CommercialProductRevenueBridgePlan;
use LBHurtado\XCommerce\Data\CommercialAllocationLineData;
use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;
use LBHurtado\XCommerce\Enums\CommercialWaterfallLineType;

it('builds deterministic Product transfer and reversal references', function (): void {
    $plan = new CommercialAllocationPlanData(
        sourceCommercialEventReference: 'sale:bridge',
        policyReference: 'policy:bridge',
        policyVersion: 1,
        currency: 'PHP',
        allocationBaseMinor: 1_000,
        lines: [new CommercialAllocationLineData(
            policyRuleReference: 'rule:product',
            sequence: 3,
            lineType: CommercialWaterfallLineType::Allocation,
            category: 'product_revenue',
            recipientReference: 'product:pay-code',
            amountMinor: 800,
            currency: 'PHP',
        )],
    );

    $first = app(CommercialProductRevenueBridgePlan::class)->build($plan, 'sale:bridge', 'treasury:product');
    $second = app(CommercialProductRevenueBridgePlan::class)->build($plan, 'sale:bridge', 'treasury:product');

    expect($first)->toBe($second)
        ->and($first['status'])->toBe('planned')
        ->and($first['source_position_reference'])->toBe('treasury:product')
        ->and($first['transfers'][0]['amount_minor'])->toBe(800)
        ->and($first['transfers'][0]['reversal_reference'])->toStartWith('commercial-product-revenue-reversal:');
});

it('does not plan principal or non Product lines', function (): void {
    $plan = new CommercialAllocationPlanData(
        sourceCommercialEventReference: 'sale:bridge',
        policyReference: 'policy:bridge',
        policyVersion: 1,
        currency: 'PHP',
        allocationBaseMinor: 1_000,
        lines: [new CommercialAllocationLineData(
            policyRuleReference: 'rule:principal',
            sequence: 1,
            lineType: CommercialWaterfallLineType::Residual,
            category: 'commercial_revenue',
            recipientReference: 'operator:x-change',
            amountMinor: 1_000,
            currency: 'PHP',
        )],
    );

    expect(app(CommercialProductRevenueBridgePlan::class)->build($plan, 'sale:bridge', 'treasury:product')['transfers'])
        ->toBe([]);
});
