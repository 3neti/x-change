<?php

declare(strict_types=1);

it('documents cockpit mutation wave 8c read model scope enforcement plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/133-wave-8c-read-model-scope-enforcement-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 8C — Read Model Scope Enforcement Plan')
        ->and($report)->toContain('Status: Scaffolded / Runtime planning recorded')
        ->and($report)->toContain('Scope enforcement should sit at read-model/provider boundaries before presentation hydration.')
        ->and($report)->toContain('Durable activity production default remains disabled.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('8D — Repository Query Scope Enforcement Plan')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 8C — Read Model Scope Enforcement Plan')
        ->and($cockpitCompass)->toContain('reports/133-wave-8c-read-model-scope-enforcement-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 8C — Read Model Scope Enforcement Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/133-wave-8c-read-model-scope-enforcement-plan.md');
});
