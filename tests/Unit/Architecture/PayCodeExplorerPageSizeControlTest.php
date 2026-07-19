<?php

declare(strict_types=1);

it('documents pay code explorer page size control slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/586-pay-code-explorer-page-size-control-slice-1.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/587-pay-code-explorer-page-size-control-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostComponent = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Page Size Control — Slice 1')
        ->toContain('10`, `25`, and `50` rows-per-page options')
        ->toContain('Presentation-only client-side density control')
        ->and($component)->toContain('visibleRecordLimitOptions = [10, 25, 50]')
        ->and($component)->toContain('selectedVisibleLimit')
        ->and($component)->toContain('cockpit-pay-code-result-page-size')
        ->and($component)->toContain('Rows')
        ->and($component)->toContain('{{ option }} per page')
        ->and($hostComponent)->toContain('visibleRecordLimitOptions = [10, 25, 50]')
        ->and($hostComponent)->toContain('selectedVisibleLimit')
        ->and($hostComponent)->toContain('cockpit-pay-code-result-page-size')
        ->and($frontendTest)->toContain('lets operators choose result density')
        ->and($frontendTest)->toContain('1–10 of 30')
        ->and($frontendTest)->toContain('11–20 of 30')
        ->and($frontendTest)->toContain('1–30 of 30')
        ->and($closure)->toContain('Pay Code Explorer Page Size Control — Slice 2 Closure')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php')
        ->and($closure)->toContain('Operators can switch to `10`, `25`, or `50` rows per page.')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Page Size Control Slice 1')
        ->and($cockpitCompass)->toContain('reports/586-pay-code-explorer-page-size-control-slice-1.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Page Size Control Slice 2')
        ->and($cockpitCompass)->toContain('reports/587-pay-code-explorer-page-size-control-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Page Size Control — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/586-pay-code-explorer-page-size-control-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Page Size Control — Slice 2')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/587-pay-code-explorer-page-size-control-slice-2-closure.md');
});
