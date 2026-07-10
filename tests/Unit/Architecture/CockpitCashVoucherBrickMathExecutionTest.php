<?php

declare(strict_types=1);

it('documents the upstream cash and voucher brickmath fix execution', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/103-cash-voucher-upstream-brickmath-fix-execution.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cash/Voucher Upstream BrickMath Fix Execution')
        ->and($report)->toContain('Status: Completed upstream')
        ->and($report)->toContain('198ff60 cash: normalize monetary floats before BrickMoney')
        ->and($report)->toContain('7a6889e voucher: verify cash persistence avoids BrickMath floats')
        ->and($report)->toContain('No voucher production code change was required.')
        ->and($report)->toContain('95 passed, 194 assertions')
        ->and($report)->toContain('387 passed, 28 skipped, 1159 assertions')
        ->and($report)->toContain('Retry Wave 5H — x-change Characterization Flip')
        ->and($cockpitCompass)->toContain('Cash/Voucher Upstream BrickMath Fix Execution')
        ->and($cockpitCompass)->toContain('reports/103-cash-voucher-upstream-brickmath-fix-execution.md')
        ->and($settlementCompass)->toContain('Cash/Voucher Upstream BrickMath Fix Execution')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/103-cash-voucher-upstream-brickmath-fix-execution.md');
});
