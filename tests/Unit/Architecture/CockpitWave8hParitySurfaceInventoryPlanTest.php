<?php

declare(strict_types=1);

it('documents cockpit mutation wave 8h parity surface inventory plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/138-wave-8h-parity-surface-inventory-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 8H — Parity Surface Inventory Plan')
        ->and($report)->toContain('Status: Scaffolded / Runtime planning recorded')
        ->and($report)->toContain('The parity audit must include `/x/dashboard`, `/x/pay-codes`, and `/x/balances`, not only the Cockpit dashboard.')
        ->and($report)->toContain('Durable activity production default remains disabled.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('8I — Parity Readiness Gate Plan')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 8H — Parity Surface Inventory Plan')
        ->and($cockpitCompass)->toContain('reports/138-wave-8h-parity-surface-inventory-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 8H — Parity Surface Inventory Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/138-wave-8h-parity-surface-inventory-plan.md');
});
