<?php

it('documents pay code explorer search results polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/553-pay-code-explorer-search-results-polish-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Search / Results Polish — Slice 1')
        ->toContain('Current Search')
        ->toContain('presentation-only')
        ->toContain('does not change routes, controllers, queries, read-model hydration')
        ->and($page)->toContain('cockpit-pay-code-explorer-current-search')
        ->and($page)->toContain('Connected service details')
        ->and($page)->toContain('Links only')
        ->and($page)->not->toContain('>navigation-only<')
        ->and($frontendTest)->toContain('cockpit-pay-code-explorer-current-search-item')
        ->and($frontendTest)->toContain('Connected service details')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Search / Results Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/553-pay-code-explorer-search-results-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Search / Results Polish Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/553-pay-code-explorer-search-results-polish-slice-1.md');
});
