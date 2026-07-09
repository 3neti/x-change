<?php

declare(strict_types=1);

it('documents the cockpit published asset sync drift validation checkpoint', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/059-published-asset-sync-drift-validation.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2I — Published Asset Sync / Drift Guard Validation')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('x-change:doctor --assets --json')
        ->and($report)->toContain('checked: 55')
        ->and($report)->toContain('ok: 52')
        ->and($report)->toContain('stale: 2')
        ->and($report)->toContain('missing: 1')
        ->and($report)->toContain('components/CockpitOperatorIssuanceActivityPanel.vue')
        ->and($report)->toContain('pages/Dashboard.vue')
        ->and($report)->toContain('types.ts')
        ->and($report)->toContain('no host mirror files were staged')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2I — Published Asset Sync / Drift Guard Validation')
        ->and($cockpitCompass)->toContain('reports/059-published-asset-sync-drift-validation.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2I — Published Asset Sync / Drift Guard Validation')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/059-published-asset-sync-drift-validation.md');
});
