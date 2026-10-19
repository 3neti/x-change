<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Commercial\CommercialProductRevenueShadowReconciler;
use LBHurtado\XCommerce\Data\CommercialAllocationLineData;
use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;
use LBHurtado\XCommerce\Enums\CommercialWaterfallLineType;

function shadowProductPlan(): CommercialAllocationPlanData
{
    return new CommercialAllocationPlanData(
        sourceCommercialEventReference: 'sale:shadow',
        policyReference: 'policy:shadow',
        policyVersion: 1,
        currency: 'PHP',
        allocationBaseMinor: 1_000,
        lines: [new CommercialAllocationLineData(
            policyRuleReference: 'rule:product',
            sequence: 1,
            lineType: CommercialWaterfallLineType::Allocation,
            category: 'product_revenue',
            recipientReference: 'product:pay-code',
            amountMinor: 800,
            currency: 'PHP',
        ), new CommercialAllocationLineData(
            policyRuleReference: 'rule:residual',
            sequence: 2,
            lineType: CommercialWaterfallLineType::Residual,
            category: 'commercial_revenue',
            recipientReference: 'operator:x-change',
            amountMinor: 200,
            currency: 'PHP',
        )],
    );
}

it('reconciles persisted Treasury product allocations in shadow mode', function (): void {
    $report = app(CommercialProductRevenueShadowReconciler::class)->reconcile(
        shadowProductPlan(),
        [
            ['category' => 'product_revenue', 'amount_minor' => 800, 'recipient_reference' => 'product:pay-code', 'status' => 'posted'],
            ['category' => 'commercial_revenue', 'amount_minor' => 200, 'recipient_reference' => 'operator:x-change', 'status' => 'posted'],
        ],
    );

    expect($report['status'])->toBe('reconciled')
        ->and($report['variance_minor'])->toBe(0);
});

it('flags a Treasury allocation variance', function (): void {
    $report = app(CommercialProductRevenueShadowReconciler::class)->reconcile(
        shadowProductPlan(),
        [['category' => 'product_revenue', 'amount_minor' => 700, 'recipient_reference' => 'product:pay-code', 'status' => 'posted']],
    );

    expect($report['status'])->toBe('attention')
        ->and($report['variance_minor'])->toBe(-100);
});
