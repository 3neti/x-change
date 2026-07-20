<?php

declare(strict_types=1);

it('documents pay code explorer voucher list rationalization slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/608-pay-code-explorer-voucher-list-rationalization-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $table = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $search = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeSearchBar.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Voucher List Rationalization — Slice 1')
        ->toContain('voucher lifecycle summary cards')
        ->toContain('Presentation-only Explorer rationalization')
        ->and($page)->toContain('Voucher status summary')
        ->and($page)->toContain('Focus the list by lifecycle state')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-current-search-disclosure"')
        ->and($table)->toContain('Type / Template')
        ->and($table)->toContain('Created')
        ->and($table)->toContain('Expires')
        ->and($table)->toContain('data-testid="cockpit-pay-code-row-secondary-facts"')
        ->and($search)->toContain('Search by code, recipient, amount, campaign, or status...')
        ->and($frontendTest)->toContain('renders the explorer shell header as a compact page intro');
});
