<?php

declare(strict_types=1);

it('documents pay code explorer primary summary compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/602-pay-code-explorer-primary-summary-compression-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Primary Summary Compression — Slice 1')
        ->toContain('Moved detailed Current Search facts behind a disclosure')
        ->toContain('Presentation-only primary summary compression')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-current-search-disclosure"')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-status-pills"')
        ->and($page)->not->toContain('Focus the list by lifecycle state')
        ->and($frontendTest)->toContain('renders the operator list summary as a compact scan strip')
        ->and($frontendTest)->toContain('cockpit-pay-code-explorer-current-search-disclosure');
});

it('documents pay code explorer primary summary compression slice 2 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/603-pay-code-explorer-primary-summary-compression-slice-2-closure.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $hostPage = file_get_contents($hostRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Primary Summary Compression — Slice 2 / Closure')
        ->toContain('Published Cockpit package assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-current-search-disclosure"')
        ->and($hostPage)->toContain('data-testid="cockpit-pay-code-explorer-current-search-disclosure"')
        ->and($hostPage)->toContain('data-testid="cockpit-pay-code-explorer-status-pills"')
        ->and($hostPage)->not->toContain('Focus the list by lifecycle state')
        ->and($compass)->toContain('Completed Pay Code Explorer Primary Summary Compression Slice 2 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Primary Summary Compression — Slice 2');
});
