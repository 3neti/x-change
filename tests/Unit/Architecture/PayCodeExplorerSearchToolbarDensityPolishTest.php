<?php

declare(strict_types=1);

it('documents pay code explorer search toolbar density polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/600-pay-code-explorer-search-toolbar-density-polish-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeSearchBar.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Search Toolbar Density Polish — Slice 1')
        ->toContain('compact toolbar')
        ->toContain('Presentation-only search toolbar density polish')
        ->and($component)->toContain('Find Pay Codes')
        ->and($component)->toContain('lg:grid-cols-[minmax(0,1fr)_12rem_auto_auto]')
        ->and($component)->toContain('data-testid="cockpit-pay-code-active-filter-summary"')
        ->and($component)->toContain('Apply')
        ->and($component)->toContain('Clear')
        ->and($frontendTest)->toContain('Find Pay Codes')
        ->and($frontendTest)->toContain('rounded-full');
});

it('documents pay code explorer search toolbar density polish slice 2 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/601-pay-code-explorer-search-toolbar-density-polish-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeSearchBar.vue');
    $hostComponent = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeSearchBar.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Search Toolbar Density Polish — Slice 2 / Closure')
        ->toContain('Published Cockpit package assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($component)->toContain('Find Pay Codes')
        ->and($hostComponent)->toContain('Find Pay Codes')
        ->and($hostComponent)->toContain('lg:grid-cols-[minmax(0,1fr)_12rem_auto_auto]')
        ->and($hostComponent)->toContain('data-testid="cockpit-pay-code-active-filter-summary"')
        ->and($compass)->toContain('Completed Pay Code Explorer Search Toolbar Density Polish Slice 2 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Search Toolbar Density Polish — Slice 2');
});
