<?php

declare(strict_types=1);

it('documents the campaign recipient activity explorer link hydration', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/284-wave-44b-campaign-recipient-activity-explorer-link-hydration.md');
    $panel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitOperatorIssuanceActivityPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDashboardHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 44B')
        ->toContain('Open in Explorer')
        ->toContain('Return to Campaign Dashboard')
        ->toContain('campaign_recipient_id')
        ->toContain('Cockpit Wave 44C — Campaign Recipient Activity Navigation UI Hardening');

    expect($panel)
        ->toContain('appendCampaignAttributionParams')
        ->toContain('campaign_recipient_id')
        ->toContain('cockpit-operator-issuance-activity-campaign-dashboard-link');

    expect($frontendTest)
        ->toContain('campaign_recipient_id=recipient-wave-43c')
        ->toContain('Return to Campaign Dashboard');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 44B result: Campaign Recipient Activity Explorer link hydration completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 44B — Campaign Recipient Activity Explorer Link Hydration');
});
