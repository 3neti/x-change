<?php

declare(strict_types=1);

it('documents pay code explorer result volume pagination polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/581-pay-code-explorer-result-volume-pagination-polish-slice-1.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/582-pay-code-explorer-result-volume-pagination-polish-slice-2-closure.md');
    $pagination = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/583-pay-code-explorer-pagination-navigation-slice-1.md');
    $paginationClosure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/584-pay-code-explorer-pagination-navigation-slice-2-closure.md');
    $metricWidth = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/585-pay-code-explorer-result-metric-width-stability.md');
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
        ->and($component)->toContain('cockpit-pay-code-result-pagination')
        ->and($component)->toContain('Page {{ currentPage }} of {{ totalPages }}')
        ->and($component)->toContain('goToNextPage')
        ->and($component)->toContain('goToPreviousPage')
        ->and($component)->toContain('sm:w-[30rem]')
        ->and($component)->toContain('font-mono')
        ->and($component)->toContain('tabular-nums')
        ->and($component)->toContain('whitespace-nowrap')
        ->and($hostComponent)->toContain('defaultVisibleRecordLimit = 25')
        ->and($hostComponent)->toContain('cockpit-pay-code-result-limit-notice')
        ->and($hostComponent)->toContain('cockpit-pay-code-result-pagination')
        ->and($hostComponent)->toContain('Page {{ currentPage }} of {{ totalPages }}')
        ->and($hostComponent)->toContain('sm:w-[30rem]')
        ->and($hostComponent)->toContain('font-mono')
        ->and($hostComponent)->toContain('tabular-nums')
        ->and($hostComponent)->toContain('whitespace-nowrap')
        ->and($frontendTest)->toContain('paginates high-volume result rendering')
        ->and($frontendTest)->toContain('keeps result metric values stable')
        ->and($frontendTest)->toContain('1–25 of 30')
        ->and($frontendTest)->toContain('26–30 of 30')
        ->and($frontendTest)->toContain('PC-VOLUME-026')
        ->and($pagination)->toContain('Pay Code Explorer Pagination Navigation — Slice 1')
        ->and($pagination)->toContain('Added client-side `Previous` and `Next` controls.')
        ->and($pagination)->toContain('Page X of Y')
        ->and($pagination)->toContain('presentation-only client-side pagination')
        ->and($paginationClosure)->toContain('Pay Code Explorer Pagination Navigation — Slice 2 Closure')
        ->and($paginationClosure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($paginationClosure)->toContain('php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php')
        ->and($paginationClosure)->toContain('High-volume Explorer results render 25 rows per page.')
        ->and($metricWidth)->toContain('Pay Code Explorer Result Metric Width Stability')
        ->and($metricWidth)->toContain('stable desktop summary width')
        ->and($metricWidth)->toContain('font-mono')
        ->and($metricWidth)->toContain('tabular-nums')
        ->and($metricWidth)->toContain('whitespace-nowrap')
        ->and($closure)->toContain('Pay Code Explorer Result Volume / Pagination Polish — Slice 2 Closure')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php')
        ->and($closure)->toContain('High-volume Explorer results render only the first 25 records by default.')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/581-pay-code-explorer-result-volume-pagination-polish-slice-1.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish Slice 2')
        ->and($cockpitCompass)->toContain('reports/582-pay-code-explorer-result-volume-pagination-polish-slice-2-closure.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Pagination Navigation Slice 1')
        ->and($cockpitCompass)->toContain('reports/583-pay-code-explorer-pagination-navigation-slice-1.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Pagination Navigation Slice 2')
        ->and($cockpitCompass)->toContain('reports/584-pay-code-explorer-pagination-navigation-slice-2-closure.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Result Metric Width Stability')
        ->and($cockpitCompass)->toContain('reports/585-pay-code-explorer-result-metric-width-stability.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/581-pay-code-explorer-result-volume-pagination-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish — Slice 2')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/582-pay-code-explorer-result-volume-pagination-polish-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Pagination Navigation — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/583-pay-code-explorer-pagination-navigation-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Pagination Navigation — Slice 2')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/584-pay-code-explorer-pagination-navigation-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Result Metric Width Stability')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/585-pay-code-explorer-result-metric-width-stability.md');
});
