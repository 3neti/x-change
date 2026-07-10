<?php

declare(strict_types=1);

it('documents the cockpit durable activity database repository baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/070-durable-activity-database-repository-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3H — Durable Activity Database Repository Baseline')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRepositoryContract')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRedactionPolicyContract')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRetentionPolicyContract')
        ->and($report)->toContain('Default binding remains null')
        ->and($report)->toContain('No provider binding changed')
        ->and($report)->toContain('No UI was changed in this slice')
        ->and($report)->toContain('Cockpit Mutation Wave 3I — Durable Activity Recorder Opt-In Boundary')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3H — Durable Activity Database Repository Baseline')
        ->and($cockpitCompass)->toContain('reports/070-durable-activity-database-repository-baseline.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3H — Durable Activity Database Repository Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/070-durable-activity-database-repository-baseline.md');
});
