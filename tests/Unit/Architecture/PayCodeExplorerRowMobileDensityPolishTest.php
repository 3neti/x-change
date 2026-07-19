<?php

it('documents pay code explorer row mobile density polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/555-pay-code-explorer-row-mobile-density-slice-1.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/556-pay-code-explorer-row-mobile-density-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');
    $hostComponent = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Row / Mobile Density Polish — Slice 1')
        ->toContain('presentation-only')
        ->toContain('does not change routes, controllers, queries, read-model hydration')
        ->and($component)->toContain('cockpit-pay-code-mobile-results')
        ->and($component)->toContain('cockpit-pay-code-mobile-row')
        ->and($component)->toContain('md:hidden')
        ->and($component)->toContain('hidden overflow-x-auto md:block')
        ->and($component)->toContain('cockpit-pay-code-mobile-row-action-link')
        ->and($frontendTest)->toContain('mobile-first Pay Code result cards')
        ->and($frontendTest)->toContain('cockpit-pay-code-mobile-row')
        ->and($hostComponent)->toContain('cockpit-pay-code-mobile-results')
        ->and($closure)->toContain('Pay Code Explorer Row / Mobile Density Polish — Slice 2 / Closure')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Row / Mobile Density Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/555-pay-code-explorer-row-mobile-density-slice-1.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Row / Mobile Density Polish Slice 2 / Closure')
        ->and($cockpitCompass)->toContain('reports/556-pay-code-explorer-row-mobile-density-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Row / Mobile Density Polish Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/555-pay-code-explorer-row-mobile-density-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Row / Mobile Density Polish Slice 2 / Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/556-pay-code-explorer-row-mobile-density-slice-2-closure.md');
});
