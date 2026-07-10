<?php

declare(strict_types=1);

it('documents the upstream brickmath fix intake gate as blocked pending upstream work', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/102-upstream-brickmath-fix-intake-characterization-flip.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5H — Upstream BrickMath Fix Intake / x-change Characterization Flip')
        ->and($report)->toContain('Status: Blocked pending upstream fix')
        ->and($report)->toContain('Do not flip the x-change characterization test in this checkpoint.')
        ->and($report)->toContain('/Users/rli/PhpstormProjects/packages/cash/src/Models/Cash.php')
        ->and($report)->toContain('/Users/rli/PhpstormProjects/packages/voucher/src/Pipelines/Voucher/PersistCash.php')
        ->and($report)->toContain('Money::of($value, $currency)->getMinorAmount()->toInt()')
        ->and($report)->toContain('Cash::create([')
        ->and($report)->toContain('1 passed, 6 assertions')
        ->and($report)->toContain('Cash/Voucher Upstream BrickMath Fix Execution')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5H — Upstream BrickMath Fix Intake / x-change Characterization Flip')
        ->and($cockpitCompass)->toContain('reports/102-upstream-brickmath-fix-intake-characterization-flip.md')
        ->and($cockpitCompass)->toContain('Cash/Voucher Upstream BrickMath Fix Execution')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 5H — Upstream BrickMath Fix Intake / x-change Characterization Flip')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/102-upstream-brickmath-fix-intake-characterization-flip.md')
        ->and($settlementCompass)->toContain('Cash/Voucher Upstream BrickMath Fix Execution');
});
