<?php

declare(strict_types=1);

it('documents pay code explorer results header compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/604-pay-code-explorer-results-header-compression-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Results Header Compression — Slice 1')
        ->toContain('Converted the results density summary into a tighter pill-style metric strip')
        ->toContain('Presentation-only results header compression')
        ->and($component)->toContain('data-testid="cockpit-pay-code-results-density-summary"')
        ->and($component)->toContain('data-testid="cockpit-pay-code-result-page-size"')
        ->and($component)->toContain('data-testid="cockpit-pay-code-result-pagination-footer"')
        ->and($frontendTest)->toContain('renders the results header as a compact pagination toolbar');
});

it('documents pay code explorer results header compression slice 2 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/605-pay-code-explorer-results-header-compression-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostComponent = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Results Header Compression — Slice 2 / Closure')
        ->toContain('Published Cockpit package assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($component)->toContain('data-testid="cockpit-pay-code-results-density-summary"')
        ->and($hostComponent)->toContain('data-testid="cockpit-pay-code-results-density-summary"')
        ->and($hostComponent)->toContain('data-testid="cockpit-pay-code-result-pagination-footer"')
        ->and($compass)->toContain('Completed Pay Code Explorer Results Header Compression Slice 2 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Results Header Compression — Slice 2');
});
