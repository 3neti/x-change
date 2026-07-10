<?php

declare(strict_types=1);

it('documents durable activity local opt in closure and expected ui effect', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/106-durable-activity-local-opt-in-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Wave 5J — Durable Activity Local Opt-In Closure')
        ->and($report)->toContain('Status: Closed locally')
        ->and($report)->toContain('Keep local durable activity repository and recorder enabled.')
        ->and($report)->toContain('DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('DatabaseCockpitOperatorIssuanceActivityRecorder')
        ->and($report)->toContain('MCPC count: 1')
        ->and($report)->toContain('The Cockpit Operator Issuance Activity panel should continue showing the real `MCPC` Quick Generate activity.')
        ->and($report)->toContain('Production defaults remain unchanged')
        ->and($report)->toContain('Wave 5K — Real Activity Production Readiness Decision')
        ->and($cockpitCompass)->toContain('Wave 5J — Durable Activity Local Opt-In Closure')
        ->and($cockpitCompass)->toContain('reports/106-durable-activity-local-opt-in-closure.md')
        ->and($settlementCompass)->toContain('Wave 5J — Durable Activity Local Opt-In Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/106-durable-activity-local-opt-in-closure.md');
});
