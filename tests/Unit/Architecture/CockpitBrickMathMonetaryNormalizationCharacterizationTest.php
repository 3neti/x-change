<?php

declare(strict_types=1);

it('documents the brickmath monetary normalization characterization boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/100-brickmath-monetary-normalization-characterization.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5F — BrickMath Monetary Normalization Characterization')
        ->and($report)->toContain('Status: Characterized')
        ->and($report)->toContain('Passing floats to BigNumber::of()')
        ->and($report)->toContain('/Users/rli/PhpstormProjects/packages/voucher/src/Pipelines/Voucher/PersistCash.php')
        ->and($report)->toContain('/Users/rli/PhpstormProjects/packages/cash/src/Models/Cash.php')
        ->and($report)->toContain('LBHurtado\Voucher\Data\CashInstructionData::$amount is typed as float.')
        ->and($report)->toContain('Do not implement the Brick\Math fix inside x-change in this checkpoint.')
        ->and($report)->toContain('tests/Feature/Actions/GeneratePayCodeIntegrationTest.php')
        ->and($report)->toContain('it characterizes the brick math float deprecation during voucher cash persistence')
        ->and($report)->toContain('1 passed, 6 assertions')
        ->and($report)->toContain('Cockpit Mutation Wave 5G — Cross-Package BrickMath Fix Instruction / Upstream Coordination')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5F — BrickMath Monetary Normalization Characterization')
        ->and($cockpitCompass)->toContain('reports/100-brickmath-monetary-normalization-characterization.md')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5G — Cross-Package BrickMath Fix Instruction / Upstream Coordination')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 5F — BrickMath Monetary Normalization Characterization')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/100-brickmath-monetary-normalization-characterization.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 5G — Cross-Package BrickMath Fix Instruction / Upstream Coordination');
});
