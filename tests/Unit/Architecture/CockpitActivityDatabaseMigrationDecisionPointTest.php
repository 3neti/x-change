<?php

declare(strict_types=1);

it('documents the cockpit activity database migration decision point before creating storage', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/067-activity-database-migration-decision-point.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3E — Database Migration Decision Point')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('Decision: Proceed with a package-owned durable activity table in the next implementation slice')
        ->and($report)->toContain('x_change_cockpit_operator_issuance_activities')
        ->and($report)->toContain('activity_id')
        ->and($report)->toContain('occurred_at')
        ->and($report)->toContain('retention_until')
        ->and($report)->toContain('redaction_flags')
        ->and($report)->toContain('safe_context')
        ->and($report)->toContain('metadata')
        ->and($report)->toContain('index_activity_id_unique')
        ->and($report)->toContain('index_operator_occurred_at')
        ->and($report)->toContain('index_subject_reference')
        ->and($report)->toContain('index_correlation_id')
        ->and($report)->toContain('No migration file was created in this slice')
        ->and($report)->toContain('No Eloquent model was introduced')
        ->and($report)->toContain('No database writes were introduced')
        ->and($report)->toContain('Cockpit Mutation Wave 3F — Durable Activity Migration Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3E — Database Migration Decision Point')
        ->and($cockpitCompass)->toContain('reports/067-activity-database-migration-decision-point.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3E — Database Migration Decision Point')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/067-activity-database-migration-decision-point.md');
});

it('documents that the decision point itself did not create the durable activity migration', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/067-activity-database-migration-decision-point.md';

    expect(file_get_contents($reportPath))
        ->toContain('No migration file was created in this slice')
        ->and(file_get_contents($reportPath))->toContain('Cockpit Mutation Wave 3F — Durable Activity Migration Baseline');
});
