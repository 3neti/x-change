<?php

declare(strict_types=1);

it('documents the distribution workspace campaign return navigation', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/301-wave-47c-distribution-workspace-campaign-return-navigation.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $frontend = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 47C')
        ->toContain('Return to Pay Code Detail · campaign context')
        ->toContain('Return to Explorer · campaign context')
        ->toContain('Return to Campaign Dashboard · read-only')
        ->toContain('Cockpit Wave 47D — Campaign Destination Return Navigation Publish / Browser Verification');

    expect($page)
        ->toContain('cockpit-distribution-campaign-detail-return-link')
        ->toContain('cockpit-distribution-campaign-explorer-return-link')
        ->toContain('cockpit-distribution-campaign-dashboard-return-link')
        ->toContain('campaignQueryString');

    expect($frontend)
        ->toContain('campaign_recipient_id=recipient-wave-46')
        ->toContain('activity_source=operator_issuance_activity')
        ->toContain('cockpit-distribution-campaign-dashboard-return-link');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 47C result: Distribution Workspace campaign return navigation completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 47C — Distribution Workspace Campaign Return Navigation');
});
