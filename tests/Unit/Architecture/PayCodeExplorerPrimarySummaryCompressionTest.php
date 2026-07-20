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
        ->and($page)->toContain('Find Pay Codes, open detail/distribution workspaces')
        ->and($page)->toContain('line-clamp-2')
        ->and($frontendTest)->toContain('renders the operator list summary as a compact scan strip')
        ->and($frontendTest)->toContain('cockpit-pay-code-explorer-current-search-disclosure');
});
