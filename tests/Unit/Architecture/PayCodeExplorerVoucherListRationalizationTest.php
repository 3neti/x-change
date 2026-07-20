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

it('documents pay code explorer voucher list rationalization slice 2 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/609-pay-code-explorer-voucher-list-rationalization-slice-2-closure.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $hostPage = file_get_contents($hostRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $table = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostTable = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $search = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeSearchBar.vue');
    $hostSearch = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeSearchBar.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Voucher List Rationalization — Slice 2 / Closure')
        ->toContain('Published Cockpit package assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($page)->toContain('Voucher status summary')
        ->and($hostPage)->toContain('Voucher status summary')
        ->and($hostPage)->toContain('Focus the list by lifecycle state')
        ->and($table)->toContain('Type / Template')
        ->and($hostTable)->toContain('Type / Template')
        ->and($hostTable)->toContain('data-testid="cockpit-pay-code-row-secondary-facts"')
        ->and($search)->toContain('Search by code, recipient, amount, campaign, or status...')
        ->and($hostSearch)->toContain('Search by code, recipient, amount, campaign, or status...')
        ->and($compass)->toContain('Completed Pay Code Explorer Voucher List Rationalization Slice 2 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Voucher List Rationalization — Slice 2');
});
