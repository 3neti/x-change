<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Commercial\CommercialProductRevenueBridgeCommissioningSimulator;
use LBHurtado\XChange\Services\Commercial\CommercialProductRevenueBridgePlan;
use LBHurtado\XCommerce\Data\CommercialAllocationLineData;
use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;
use LBHurtado\XCommerce\Enums\CommercialWaterfallLineType;

it('commissions Product revenue posting, replay, and reversal exactly once', function (): void {
    $plan = app(CommercialProductRevenueBridgePlan::class)->build(
        new CommercialAllocationPlanData(
            sourceCommercialEventReference: 'sale:commissioning',
            policyReference: 'policy:commissioning',
            policyVersion: 1,
            currency: 'PHP',
            allocationBaseMinor: 800,
            lines: [new CommercialAllocationLineData(
                policyRuleReference: 'rule:product',
                sequence: 1,
                lineType: CommercialWaterfallLineType::Allocation,
                category: 'product_revenue',
                recipientReference: 'product:pay-code',
                amountMinor: 800,
                currency: 'PHP',
            )],
        ),
        'sale:commissioning',
        'treasury:product',
    );
    $simulator = new CommercialProductRevenueBridgeCommissioningSimulator;

    $posted = $simulator->execute($plan);
    $replayed = $simulator->execute($plan);
    $reversed = $simulator->reverse($plan);
    $replayedReversal = $simulator->reverse($plan);

    expect($posted)->toHaveCount(1)
        ->and($replayed)->toBe($posted)
        ->and($posted[array_key_first($posted)]['amount_minor'])->toBe(800)
        ->and($reversed)->toHaveCount(1)
        ->and($replayedReversal)->toBe($reversed)
        ->and($reversed[array_key_first($reversed)]['status'])->toBe('reversed');
});
