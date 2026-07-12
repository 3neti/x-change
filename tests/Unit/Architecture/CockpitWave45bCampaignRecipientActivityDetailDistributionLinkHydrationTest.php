<?php

declare(strict_types=1);

it('documents the campaign recipient activity detail distribution link hydration', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/289-wave-45b-campaign-recipient-activity-detail-distribution-link-hydration.md');
    $panel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitOperatorIssuanceActivityPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDashboardHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 45B')
        ->toContain('Open Pay Code · campaign context')
        ->toContain('Open Distribution workspace · campaign context')
        ->toContain('non-read-only attribution')
        ->toContain('Cockpit Wave 45C — Campaign Recipient Activity Detail / Distribution UI Hardening');

    expect($panel)
        ->toContain('safeDetailContextHref')
        ->toContain('safeDistributionHref')
        ->toContain('cockpit-operator-issuance-activity-distribution-link')
        ->toContain('!campaignAttribution.readOnly');

    expect($frontendTest)
        ->toContain('/x/cockpit/pay-codes/PC-1234/distribution?campaign_planning_key=plan-wave-43c')
        ->toContain('/x/cockpit/pay-codes/PC-1234/distribution');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 45B result: Campaign Recipient Activity Detail / Distribution link hydration completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 45B — Campaign Recipient Activity Detail / Distribution Link Hydration');
});
