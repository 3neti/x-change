<?php

declare(strict_types=1);

it('documents cockpit mutation wave 8b durable activity scope contract plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/132-wave-8b-durable-activity-scope-contract-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 8B — Durable Activity Scope DTO / Decision Contract Plan')
        ->and($report)->toContain('Status: Scaffolded / Runtime planning recorded')
        ->and($report)->toContain('Introduce future package-local scope and access decision DTOs before repository/query enforcement.')
        ->and($report)->toContain('Durable activity production default remains disabled.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('8C — Read Model Scope Enforcement Plan')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 8B — Durable Activity Scope DTO / Decision Contract Plan')
        ->and($cockpitCompass)->toContain('reports/132-wave-8b-durable-activity-scope-contract-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 8B — Durable Activity Scope DTO / Decision Contract Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/132-wave-8b-durable-activity-scope-contract-plan.md');
});
