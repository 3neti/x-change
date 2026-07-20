<?php

declare(strict_types=1);

it('documents pay code explorer compact operations table slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/610-pay-code-explorer-compact-operations-table-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $search = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeSearchBar.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Compact Operations Table — Slice 1')
        ->toContain('slim status pills')
        ->toContain('Presentation-only Pay Code Explorer compactness slice')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-status-pills"')
        ->and($page)->toContain('Search, filter, and open read-only Pay Code workspaces.')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-current-search-disclosure"')
        ->and($page)->not->toContain('Focus the list by lifecycle state')
        ->and($search)->toContain('Search Pay Codes')
        ->and($search)->toContain('h-9')
        ->and($search)->not->toContain('Search and filters only change this list.')
        ->and($frontendTest)->toContain('renders the operator list summary as a compact scan strip')
        ->and($frontendTest)->toContain('cockpit-pay-code-explorer-status-pills');
});
