<?php

it('documents pay code explorer campaign context visual polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/557-pay-code-explorer-campaign-context-polish-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitCampaignExplorerNavigation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Campaign Context Visual Polish — Slice 1')
        ->toContain('presentation-only')
        ->toContain('does not change routes, controllers, queries, read-model hydration')
        ->and($page)->toContain('Showing Pay Codes from a campaign view')
        ->and($page)->toContain('cockpit-campaign-navigation-primary-context')
        ->and($page)->toContain('Campaign filter details')
        ->and($page)->not->toContain('{{ campaignNavigationContext.mutation?.reason }}')
        ->and($frontendTest)->toContain('Showing Pay Codes from a campaign view')
        ->and($frontendTest)->toContain('cockpit-campaign-navigation-primary-context-item')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Campaign Context Visual Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/557-pay-code-explorer-campaign-context-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Campaign Context Visual Polish Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/557-pay-code-explorer-campaign-context-polish-slice-1.md');
});
