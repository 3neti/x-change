<?php

declare(strict_types=1);

it('documents cockpit mutation wave 8d repository query scope enforcement plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/134-wave-8d-repository-query-scope-enforcement-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 8D — Repository Query Scope Enforcement Plan')
        ->and($report)->toContain('Status: Scaffolded / Runtime planning recorded')
        ->and($report)->toContain('Repository reads should accept explicit query/scope constraints before production use.')
        ->and($report)->toContain('Durable activity production default remains disabled.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('8E — Retention Enforcement Runtime Plan')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 8D — Repository Query Scope Enforcement Plan')
        ->and($cockpitCompass)->toContain('reports/134-wave-8d-repository-query-scope-enforcement-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 8D — Repository Query Scope Enforcement Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/134-wave-8d-repository-query-scope-enforcement-plan.md');
});
