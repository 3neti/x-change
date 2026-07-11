<?php

declare(strict_types=1);

it('documents cockpit mutation wave 8g handoff runtime enablement gate plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/137-wave-8g-handoff-runtime-enablement-gate-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 8G — Handoff Runtime Enablement Gate Plan')
        ->and($report)->toContain('Status: Scaffolded / Runtime planning recorded')
        ->and($report)->toContain('Journal/action/feedback handoffs remain disabled by default and require independent explicit enablement gates.')
        ->and($report)->toContain('Durable activity production default remains disabled.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('8H — Parity Surface Inventory Plan')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 8G — Handoff Runtime Enablement Gate Plan')
        ->and($cockpitCompass)->toContain('reports/137-wave-8g-handoff-runtime-enablement-gate-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 8G — Handoff Runtime Enablement Gate Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/137-wave-8g-handoff-runtime-enablement-gate-plan.md');
});
