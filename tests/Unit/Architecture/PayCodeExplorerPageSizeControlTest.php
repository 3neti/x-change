<?php

declare(strict_types=1);

it('documents pay code explorer page size control slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/586-pay-code-explorer-page-size-control-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Page Size Control — Slice 1')
        ->toContain('10`, `25`, and `50` rows-per-page options')
        ->toContain('Presentation-only client-side density control')
        ->and($component)->toContain('visibleRecordLimitOptions = [10, 25, 50]')
        ->and($component)->toContain('selectedVisibleLimit')
        ->and($component)->toContain('cockpit-pay-code-result-page-size')
        ->and($component)->toContain('Rows')
        ->and($component)->toContain('{{ option }} per page')
        ->and($frontendTest)->toContain('lets operators choose result density')
        ->and($frontendTest)->toContain('1–10 of 30')
        ->and($frontendTest)->toContain('11–20 of 30')
        ->and($frontendTest)->toContain('1–30 of 30');
});
