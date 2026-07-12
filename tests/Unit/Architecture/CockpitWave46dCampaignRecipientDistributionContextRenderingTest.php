<?php

declare(strict_types=1);

it('documents the campaign recipient distribution context rendering', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/296-wave-46d-campaign-recipient-distribution-context-rendering.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $frontend = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 46D')
        ->toContain('Campaign recipient context')
        ->toContain('distribution_workspace')
        ->toContain('Cockpit Wave 46E — Campaign Recipient Detail / Distribution Publish / Browser Verification');

    expect($page)
        ->toContain('cockpit-distribution-campaign-navigation-context')
        ->toContain('Inspecting distribution from campaign activity')
        ->toContain("destination !== 'distribution_workspace'");

    expect($frontend)
        ->toContain('renders safe campaign recipient context on distribution workspace without dispatch controls')
        ->toContain('must-not-render')
        ->toContain('does not render campaign context on distribution workspace for the wrong destination');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 46D result: Campaign Recipient Distribution context rendering completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 46D — Campaign Recipient Distribution Context Rendering');
});
