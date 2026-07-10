<?php

declare(strict_types=1);

it('documents the cockpit durable activity migration baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/068-durable-activity-migration-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3F — Durable Activity Migration Baseline')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('2026_07_10_000400_create_x_change_cockpit_operator_issuance_activities_table.php')
        ->and($report)->toContain('x_change_cockpit_operator_issuance_activities')
        ->and($report)->toContain('index_activity_id_unique')
        ->and($report)->toContain('index_operator_occurred_at')
        ->and($report)->toContain('index_subject_reference')
        ->and($report)->toContain('index_correlation_id')
        ->and($report)->toContain('index_retention_until')
        ->and($report)->toContain('No Eloquent model was introduced')
        ->and($report)->toContain('No database repository was introduced')
        ->and($report)->toContain('No database writes were introduced')
        ->and($report)->toContain('No UI was changed in this slice')
        ->and($report)->toContain('Cockpit Mutation Wave 3G — Durable Activity Model Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3F — Durable Activity Migration Baseline')
        ->and($cockpitCompass)->toContain('reports/068-durable-activity-migration-baseline.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3F — Durable Activity Migration Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/068-durable-activity-migration-baseline.md');
});
