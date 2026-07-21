<?php

declare(strict_types=1);

it('documents pay code explorer table content density slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/616-pay-code-explorer-table-content-density-slice-1.md');
    $table = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Table Content Density — Slice 1')
        ->toContain('compact identity and lifecycle columns')
        ->toContain('Presentation-only table content density')
        ->and($table)->toContain('data-testid="cockpit-pay-code-row-identity"')
        ->and($table)->toContain('data-testid="cockpit-pay-code-row-lifecycle-dates"')
        ->and($table)->toContain('Lifecycle dates')
        ->and($table)->toContain('min-w-[52rem]')
        ->and($frontendTest)->toContain('groups desktop row identity and lifecycle facts into compact scan columns');
});
