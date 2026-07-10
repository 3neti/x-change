<?php

declare(strict_types=1);

it('documents the real activity fixture cleanup execution and ui effect', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/105-real-activity-fixture-cleanup-execution.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Wave 5I — Real Activity Fixture Cleanup Decision / Execution')
        ->and($report)->toContain('Status: Completed locally')
        ->and($report)->toContain('Remove the synthetic PC-LOCAL-DIAGNOSTIC fixture row.')
        ->and($report)->toContain('fixture_count: 0')
        ->and($report)->toContain('subject_reference: MCPC')
        ->and($report)->toContain('The synthetic `PC-LOCAL-DIAGNOSTIC` diagnostic card should no longer appear')
        ->and($report)->toContain('Wave 5J — Durable Activity Local Opt-In Closure')
        ->and($cockpitCompass)->toContain('Wave 5I — Real Activity Fixture Cleanup Decision / Execution')
        ->and($cockpitCompass)->toContain('reports/105-real-activity-fixture-cleanup-execution.md')
        ->and($settlementCompass)->toContain('Wave 5I — Real Activity Fixture Cleanup Decision / Execution')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/105-real-activity-fixture-cleanup-execution.md');
});
