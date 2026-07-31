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
        ->and($table)->toContain('data-testid="cockpit-pay-code-instructions"')
        ->and($table)->toContain('data-testid="cockpit-pay-code-party"')
        ->and($table)->toContain('min-w-[68rem]')
        ->and($frontendTest)->toContain('groups desktop capability instructions and party facts into compact scan columns');
});

it('documents pay code explorer table content density slice 2', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/617-pay-code-explorer-table-content-density-slice-2.md');
    $table = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Table Content Density — Slice 2')
        ->toContain('compact mobile fact hierarchy')
        ->toContain('Presentation-only mobile content density')
        ->and($table)->toContain('data-testid="cockpit-pay-code-mobile-instructions"')
        ->and($table)->toContain('data-testid="cockpit-pay-code-mobile-party"')
        ->and($table)->toContain('data-testid="cockpit-pay-code-mobile-row-secondary-facts"')
        ->and($frontendTest)->toContain('keeps mobile row facts compact without duplicating identity content');
});

it('documents pay code explorer table content density slice 3 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/618-pay-code-explorer-table-content-density-slice-3-closure.md');
    $table = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostTable = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Table Content Density — Slice 3 / Closure')
        ->toContain('Published package-owned Cockpit assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($hostTable)->toContain($table)
        ->and($hostTable)->toContain('data-testid="cockpit-pay-code-row-identity"')
        ->and($hostTable)->toContain('data-testid="cockpit-pay-code-instructions"')
        ->and($hostTable)->toContain('data-testid="cockpit-pay-code-mobile-instructions"')
        ->and($compass)->toContain('Completed Pay Code Explorer Table Content Density Slice 3 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Table Content Density — Slice 3');
});
