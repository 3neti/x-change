<?php

declare(strict_types=1);

it('documents the cockpit durable activity model baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/069-durable-activity-model-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3G — Durable Activity Model Baseline')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('CockpitOperatorIssuanceActivity')
        ->and($report)->toContain('x_change_cockpit_operator_issuance_activities')
        ->and($report)->toContain('safe_context')
        ->and($report)->toContain('redaction_flags')
        ->and($report)->toContain('metadata')
        ->and($report)->toContain('occurred_at')
        ->and($report)->toContain('retention_until')
        ->and($report)->toContain('No database repository was introduced')
        ->and($report)->toContain('No repository binding changed')
        ->and($report)->toContain('No database writes were introduced')
        ->and($report)->toContain('No UI was changed in this slice')
        ->and($report)->toContain('Cockpit Mutation Wave 3H — Durable Activity Database Repository Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3G — Durable Activity Model Baseline')
        ->and($cockpitCompass)->toContain('reports/069-durable-activity-model-baseline.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3G — Durable Activity Model Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/069-durable-activity-model-baseline.md');
});
