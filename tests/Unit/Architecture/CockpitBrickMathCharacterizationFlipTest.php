<?php

declare(strict_types=1);

it('documents the x-change brickmath characterization flip', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/104-x-change-brickmath-characterization-flip.md';
    $featureTestPath = $packageRoot.'/tests/Feature/Actions/GeneratePayCodeIntegrationTest.php';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $featureTest = file_get_contents($featureTestPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Retry Wave 5H — x-change Characterization Flip')
        ->and($report)->toContain('Status: Completed')
        ->and($report)->toContain('Expecting [] not to be empty.')
        ->and($report)->toContain('198ff60 cash: normalize monetary floats before BrickMoney')
        ->and($report)->toContain('7a6889e voucher: verify cash persistence avoids BrickMath floats')
        ->and($report)->toContain('Wave 5I — Real Activity Fixture Cleanup Decision / Execution')
        ->and($featureTest)->toContain('does not emit the brick math float deprecation during voucher cash persistence')
        ->and($featureTest)->toContain('->and($deprecations)->toBeEmpty();')
        ->and($cockpitCompass)->toContain('Retry Wave 5H — x-change Characterization Flip')
        ->and($cockpitCompass)->toContain('reports/104-x-change-brickmath-characterization-flip.md')
        ->and($settlementCompass)->toContain('Retry Wave 5H — x-change Characterization Flip')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/104-x-change-brickmath-characterization-flip.md');
});
