<?php

it('documents pay code explorer filter builder density polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/559-pay-code-explorer-filter-builder-density-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeFilterBuilder.vue');
    $foundationTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerFoundation.test.ts');
    $campaignTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitCampaignExplorerNavigation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Filter Builder Density Polish — Slice 1')
        ->toContain('presentation-only')
        ->toContain('does not change routes, controllers, queries, read-model hydration')
        ->and($component)->toContain('cockpit-pay-code-filter-density-summary')
        ->and($component)->toContain('activeFilterCount')
        ->and($component)->toContain('contextFilterCount')
        ->and($component)->toContain('<details')
        ->and($foundationTest)->toContain('Read-only query criteria')
        ->and($campaignTest)->toContain('cockpit-pay-code-filter-density-summary')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Filter Builder Density Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/559-pay-code-explorer-filter-builder-density-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Filter Builder Density Polish Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/559-pay-code-explorer-filter-builder-density-slice-1.md');
});
