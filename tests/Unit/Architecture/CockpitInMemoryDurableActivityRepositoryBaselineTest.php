<?php

declare(strict_types=1);

it('documents the cockpit in-memory durable activity repository baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/065-in-memory-durable-activity-repository-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3C — In-Memory Durable Activity Repository Baseline')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('InMemoryCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('implements `CockpitOperatorIssuanceActivityRepositoryContract`')
        ->and($report)->toContain('stores records in process memory')
        ->and($report)->toContain('overwrites duplicate `activity_id` records')
        ->and($report)->toContain('returns recent records newest-first by `occurred_at`')
        ->and($report)->toContain('filters recent records by')
        ->and($report)->toContain('NullCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('not made the default Cockpit runtime storage')
        ->and($report)->toContain('migrations')
        ->and($report)->toContain('Eloquent models')
        ->and($report)->toContain('database writes')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('UI changes')
        ->and($report)->toContain('No UI was changed in this slice')
        ->and($report)->toContain('6 passed, 24 assertions')
        ->and($report)->toContain('Cockpit Mutation Wave 3D — Activity Redaction and Retention Policy Contracts')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3C — In-Memory Durable Activity Repository Baseline')
        ->and($cockpitCompass)->toContain('reports/065-in-memory-durable-activity-repository-baseline.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3C — In-Memory Durable Activity Repository Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/065-in-memory-durable-activity-repository-baseline.md');
});
