<?php

declare(strict_types=1);

it('documents the cockpit durable activity storage boundary plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/063-durable-activity-storage-boundary-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3A — Durable Activity Storage Boundary Plan')
        ->and($report)->toContain('Status: Implemented as planning-only boundary')
        ->and($report)->toContain('operator_issuance_activities')
        ->and($report)->toContain('Redaction Policy')
        ->and($report)->toContain('Retention Policy')
        ->and($report)->toContain('Correlation Policy')
        ->and($report)->toContain('Read Model Policy')
        ->and($report)->toContain('Wave 3B — Durable Activity DTO and Repository Contract')
        ->and($report)->toContain('This slice does not authorize')
        ->and($report)->toContain('migrations')
        ->and($report)->toContain('Eloquent models')
        ->and($report)->toContain('database writes')
        ->and($report)->toContain('repositories')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('raw payload persistence')
        ->and($report)->toContain('UI changes')
        ->and($report)->toContain('money movement')
        ->and($report)->toContain('No UI was changed in this slice')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3A — Durable Activity Storage Boundary Plan')
        ->and($cockpitCompass)->toContain('reports/063-durable-activity-storage-boundary-plan.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3A — Durable Activity Storage Boundary Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/063-durable-activity-storage-boundary-plan.md');
});
