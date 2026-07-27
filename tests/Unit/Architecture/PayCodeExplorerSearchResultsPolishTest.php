<?php

it('documents pay code explorer search results polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/553-pay-code-explorer-search-results-polish-slice-1.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/554-pay-code-explorer-search-results-polish-slice-2-closure.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');
    $browserTest = file_get_contents($packageRoot.'/../../tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Search / Results Polish — Slice 1')
        ->toContain('Current Search')
        ->toContain('presentation-only')
        ->toContain('does not change routes, controllers, queries, read-model hydration')
        ->and($page)->toContain('cockpit-pay-code-explorer-current-search')
        ->and($page)->toContain('Technical details')
        ->and($page)->toContain('Links only')
        ->and($page)->not->toContain('>navigation-only<')
        ->and($frontendTest)->toContain('cockpit-pay-code-explorer-current-search-item')
        ->and($frontendTest)->toContain('Technical details')
        ->and($browserTest)->toContain('CURRENT SEARCH')
        ->and($browserTest)->toContain('List totals')
        ->and($closure)->toContain('Pay Code Explorer Search / Results Polish — Slice 2 / Closure')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Search / Results Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/553-pay-code-explorer-search-results-polish-slice-1.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Search / Results Polish Slice 2 / Closure')
        ->and($cockpitCompass)->toContain('reports/554-pay-code-explorer-search-results-polish-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Search / Results Polish Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/553-pay-code-explorer-search-results-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Search / Results Polish Slice 2 / Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/554-pay-code-explorer-search-results-polish-slice-2-closure.md');
});
