<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Commercial\CommercialProductRevenueReconciliation;
use LBHurtado\XCommerce\Data\CommercialAllocationLineData;
use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;
use LBHurtado\XCommerce\Enums\CommercialWaterfallLineType;

it('reconciles product revenue without including Pay Code principal', function (): void {
    $plan = new CommercialAllocationPlanData(
        sourceCommercialEventReference: 'sale:one',
        policyReference: 'policy:one',
        policyVersion: 1,
        currency: 'PHP',
        allocationBaseMinor: 1_150,
        lines: [
            new CommercialAllocationLineData(
                policyRuleReference: 'rule:product',
                sequence: 1,
                lineType: CommercialWaterfallLineType::Allocation,
                category: 'product_revenue',
                recipientReference: 'product:pay-code',
                amountMinor: 800,
                currency: 'PHP',
            ),
            new CommercialAllocationLineData(
                policyRuleReference: 'rule:commercial',
                sequence: 2,
                lineType: CommercialWaterfallLineType::Residual,
                category: 'commercial_revenue',
                recipientReference: 'operator:x-change',
                amountMinor: 350,
                currency: 'PHP',
            ),
        ],
    );

    $report = app(CommercialProductRevenueReconciliation::class)->reconcile($plan);

    expect($report['status'])->toBe('reconciled')
        ->and($report['product_revenue_minor'])->toBe(800)
        ->and($report['non_product_minor'])->toBe(350)
        ->and($report['principal_minor'])->toBe(0)
        ->and($report['checks'])->toMatchArray([
            'quote_conserved' => true,
            'product_lines_governed' => true,
            'principal_separate' => true,
        ]);
});

it('flags a plan that mixes principal into the commercial allocation', function (): void {
    $plan = new CommercialAllocationPlanData(
        sourceCommercialEventReference: 'sale:invalid',
        policyReference: 'policy:one',
        policyVersion: 1,
        currency: 'PHP',
        allocationBaseMinor: 1_000,
        lines: [new CommercialAllocationLineData(
            policyRuleReference: 'rule:principal',
            sequence: 1,
            lineType: CommercialWaterfallLineType::Allocation,
            category: 'principal',
            recipientReference: 'pay-code:principal',
            amountMinor: 1_000,
            currency: 'PHP',
        )],
    );

    $report = app(CommercialProductRevenueReconciliation::class)->reconcile($plan);

    expect($report['status'])->toBe('attention')
        ->and($report['checks']['principal_separate'])->toBeFalse();
});
