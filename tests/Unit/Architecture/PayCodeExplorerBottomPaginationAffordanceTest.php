<?php

declare(strict_types=1);

it('documents pay code explorer bottom pagination affordance slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/588-pay-code-explorer-bottom-pagination-affordance-slice-1.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/589-pay-code-explorer-bottom-pagination-affordance-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostComponent = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Bottom Pagination Affordance — Slice 1')
        ->toContain('footer pagination bar')
        ->toContain('Presentation-only client-side pagination affordance')
        ->and($component)->toContain('cockpit-pay-code-result-pagination-footer')
        ->and($component)->toContain('Pay Code result pages footer')
        ->and($component)->toContain('Showing {{ firstVisibleRecordNumber }}')
        ->and($component)->toContain('lastVisibleRecordNumber')
        ->and($component)->toContain('of {{ records.length }}')
        ->and($component)->toContain('cockpit-pay-code-result-pagination-footer-previous')
        ->and($component)->toContain('cockpit-pay-code-result-pagination-footer-next')
        ->and($hostComponent)->toContain('cockpit-pay-code-result-pagination-footer')
        ->and($hostComponent)->toContain('cockpit-pay-code-result-pagination-footer-previous')
        ->and($hostComponent)->toContain('cockpit-pay-code-result-pagination-footer-next')
        ->and($frontendTest)->toContain('lets operators page from the footer')
        ->and($frontendTest)->toContain('PC-FOOTER-030')
        ->and($closure)->toContain('Pay Code Explorer Bottom Pagination Affordance — Slice 2 Closure')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php')
        ->and($closure)->toContain('pagination controls above and below')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Bottom Pagination Affordance Slice 1')
        ->and($cockpitCompass)->toContain('reports/588-pay-code-explorer-bottom-pagination-affordance-slice-1.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Bottom Pagination Affordance Slice 2')
        ->and($cockpitCompass)->toContain('reports/589-pay-code-explorer-bottom-pagination-affordance-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Bottom Pagination Affordance — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/588-pay-code-explorer-bottom-pagination-affordance-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Bottom Pagination Affordance — Slice 2')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/589-pay-code-explorer-bottom-pagination-affordance-slice-2-closure.md');
});
