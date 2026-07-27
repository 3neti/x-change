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
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-command-card"')
        ->and($page)->toContain('Search, filter, and open read-only Pay Code')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-current-search-disclosure"')
        ->and($page)->not->toContain('Focus the list by lifecycle state')
        ->and($search)->toContain('Search Pay Codes')
        ->and($search)->toContain('h-9')
        ->and($search)->not->toContain('Search and filters only change this list.')
        ->and($frontendTest)->toContain('renders the operator list summary as a compact scan strip')
        ->and($frontendTest)->toContain('cockpit-pay-code-explorer-primary-summary');
});

it('documents pay code explorer compact operations table slice 2', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/611-pay-code-explorer-compact-operations-table-slice-2.md');
    $table = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Compact Operations Table — Slice 2')
        ->toContain('icon-first desktop row actions')
        ->toContain('Presentation-only table compactness slice')
        ->and($table)->toContain('lucide-vue-next')
        ->and($table)->toContain('h-8 w-8 items-center justify-center')
        ->and($table)->toContain('flex justify-end gap-1.5')
        ->and($table)->toContain('px-4 py-2.5')
        ->and($table)->toContain('MoreHorizontal')
        ->and($frontendTest)->toContain('aria-label')
        ->and($frontendTest)->toContain('py-2.5');
});

it('documents pay code explorer compact operations table slice 3 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/612-pay-code-explorer-compact-operations-table-slice-3-closure.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $hostPage = file_get_contents($hostRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $table = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostTable = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $search = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeSearchBar.vue');
    $hostSearch = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeSearchBar.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Compact Operations Table — Slice 3 / Closure')
        ->toContain('Published package-owned Cockpit assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-command-card"')
        ->and($hostPage)->toContain('data-testid="cockpit-pay-code-explorer-command-card"')
        ->and($search)->toContain('Search Pay Codes')
        ->and($hostSearch)->toContain('Search Pay Codes')
        ->and($hostSearch)->toContain('h-9')
        ->and($table)->toContain('h-8 w-8 items-center justify-center')
        ->and($hostTable)->toContain('h-8 w-8 items-center justify-center')
        ->and($hostTable)->toContain('MoreHorizontal')
        ->and($compass)->toContain('Completed Pay Code Explorer Compact Operations Table Slice 3 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Compact Operations Table — Slice 3');
});
