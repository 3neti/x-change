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
