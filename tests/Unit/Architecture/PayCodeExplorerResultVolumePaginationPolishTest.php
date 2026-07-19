<?php

declare(strict_types=1);

it('documents pay code explorer result volume pagination polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/581-pay-code-explorer-result-volume-pagination-polish-slice-1.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/582-pay-code-explorer-result-volume-pagination-polish-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostComponent = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Result Volume / Pagination Polish — Slice 1')
        ->toContain('Limited default rendered Pay Code rows to the first 25 records.')
        ->toContain('Showing N of Total')
        ->toContain('presentation-only client-side result limiting')
        ->and($component)->toContain('defaultVisibleRecordLimit = 25')
        ->and($component)->toContain('visibleRecords')
        ->and($component)->toContain('cockpit-pay-code-result-limit-notice')
        ->and($component)->toContain('Showing the first')
        ->and($hostComponent)->toContain('defaultVisibleRecordLimit = 25')
        ->and($hostComponent)->toContain('cockpit-pay-code-result-limit-notice')
        ->and($frontendTest)->toContain('limits high-volume result rendering')
        ->and($frontendTest)->toContain('25 of 30')
        ->and($frontendTest)->toContain('PC-VOLUME-026')
        ->and($closure)->toContain('Pay Code Explorer Result Volume / Pagination Polish — Slice 2 Closure')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php')
        ->and($closure)->toContain('High-volume Explorer results render only the first 25 records by default.')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/581-pay-code-explorer-result-volume-pagination-polish-slice-1.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish Slice 2')
        ->and($cockpitCompass)->toContain('reports/582-pay-code-explorer-result-volume-pagination-polish-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/581-pay-code-explorer-result-volume-pagination-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish — Slice 2')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/582-pay-code-explorer-result-volume-pagination-polish-slice-2-closure.md');
});
