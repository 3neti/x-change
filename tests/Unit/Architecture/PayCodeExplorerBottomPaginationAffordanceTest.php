<?php

declare(strict_types=1);

it('documents pay code explorer bottom pagination affordance slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/588-pay-code-explorer-bottom-pagination-affordance-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Bottom Pagination Affordance — Slice 1')
        ->toContain('footer pagination bar')
        ->toContain('Presentation-only client-side pagination affordance')
        ->and($component)->toContain('cockpit-pay-code-result-pagination-footer')
        ->and($component)->toContain('Pay Code result pages footer')
        ->and($component)->toContain('Showing {{ firstVisibleRecordNumber }}–{{ lastVisibleRecordNumber }} of {{ records.length }}')
        ->and($component)->toContain('cockpit-pay-code-result-pagination-footer-previous')
        ->and($component)->toContain('cockpit-pay-code-result-pagination-footer-next')
        ->and($frontendTest)->toContain('lets operators page from the footer')
        ->and($frontendTest)->toContain('PC-FOOTER-030');
});
