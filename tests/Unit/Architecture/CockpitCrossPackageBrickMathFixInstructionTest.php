<?php

declare(strict_types=1);

it('documents the cross package brickmath fix instruction and upstream coordination boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/101-cross-package-brickmath-fix-instruction-upstream-coordination.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5G — Cross-Package BrickMath Fix Instruction / Upstream Coordination')
        ->and($report)->toContain('Status: Instruction drafted')
        ->and($report)->toContain('/Users/rli/PhpstormProjects/packages/cash')
        ->and($report)->toContain('/Users/rli/PhpstormProjects/packages/voucher')
        ->and($report)->toContain('Cash::amount set mutator should cast numeric floats to string before calling Money::of().')
        ->and($report)->toContain('Voucher PersistCash should pass a decimal string or Money-compatible value into Cash::create().')
        ->and($report)->toContain('Do not change voucher production code if the cash package fix eliminates the deprecation for voucher generation.')
        ->and($report)->toContain('Update the Wave 5F characterization test so it expects no `Passing floats to BigNumber::of()` warning during real `GeneratePayCode`.')
        ->and($report)->toContain('Do not flip the x-change characterization test until upstream package fixes are applied and verified.')
        ->and($report)->toContain('cash: normalize monetary floats before BrickMoney')
        ->and($report)->toContain('voucher: verify cash persistence avoids BrickMath floats')
        ->and($report)->toContain('cockpit: verify brickmath monetary warning is resolved')
        ->and($report)->toContain('Cockpit Mutation Wave 5H — Upstream BrickMath Fix Intake / x-change Characterization Flip')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5G — Cross-Package BrickMath Fix Instruction / Upstream Coordination')
        ->and($cockpitCompass)->toContain('reports/101-cross-package-brickmath-fix-instruction-upstream-coordination.md')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5H — Upstream BrickMath Fix Intake / x-change Characterization Flip')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 5G — Cross-Package BrickMath Fix Instruction / Upstream Coordination')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/101-cross-package-brickmath-fix-instruction-upstream-coordination.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 5H — Upstream BrickMath Fix Intake / x-change Characterization Flip');
});
