<?php

declare(strict_types=1);

it('documents pay code explorer result volume pagination polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/581-pay-code-explorer-result-volume-pagination-polish-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
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
        ->and($frontendTest)->toContain('limits high-volume result rendering')
        ->and($frontendTest)->toContain('25 of 30')
        ->and($frontendTest)->toContain('PC-VOLUME-026')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/581-pay-code-explorer-result-volume-pagination-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Result Volume / Pagination Polish — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/581-pay-code-explorer-result-volume-pagination-polish-slice-1.md');
});
