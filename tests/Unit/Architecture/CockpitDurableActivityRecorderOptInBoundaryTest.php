<?php

declare(strict_types=1);

it('documents the cockpit durable activity recorder opt-in boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/071-durable-activity-recorder-opt-in-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3I — Durable Activity Recorder Opt-In Boundary')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('DatabaseCockpitOperatorIssuanceActivityRecorder')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRecorderContract')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRepositoryContract')
        ->and($report)->toContain('Default recorder binding remains null')
        ->and($report)->toContain('No provider binding changed')
        ->and($report)->toContain('No UI was changed in this slice')
        ->and($report)->toContain('Cockpit Mutation Wave 3J — Durable Activity Runtime Opt-In Configuration')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3I — Durable Activity Recorder Opt-In Boundary')
        ->and($cockpitCompass)->toContain('reports/071-durable-activity-recorder-opt-in-boundary.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3I — Durable Activity Recorder Opt-In Boundary')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/071-durable-activity-recorder-opt-in-boundary.md');
});
