<?php

declare(strict_types=1);

it('documents the distribution workspace campaign context copy refinement', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/306-wave-48c-distribution-context-copy-refinement.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $frontend = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 48C')
        ->toContain('Completed')
        ->toContain('Back to Pay Code Detail · read-only')
        ->toContain('No campaign mutation');

    expect($page)
        ->toContain('Inspecting distribution from campaign activity')
        ->toContain('only move between read-only Cockpit views')
        ->toContain('Back to Campaign Dashboard · read-only');

    expect($frontend)
        ->toContain('Inspecting distribution from campaign activity')
        ->toContain('Back to Pay Code Detail')
        ->toContain('campaign_recipient_id=recipient-wave-46');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 48C result: Distribution Workspace context copy refinement completed')
        ->toContain('Cockpit Wave 48D — Campaign Destination Context Copy Publish / Browser Verification');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 48C — Distribution Workspace Context Copy Refinement')
        ->toContain('Cockpit Wave 48D — Campaign Destination Context Copy Publish / Browser Verification');
});
