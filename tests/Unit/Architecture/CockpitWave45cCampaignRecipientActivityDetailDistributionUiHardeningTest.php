<?php

declare(strict_types=1);

it('documents the campaign recipient activity detail distribution ui hardening', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/290-wave-45c-campaign-recipient-activity-detail-distribution-ui-hardening.md');
    $panel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitOperatorIssuanceActivityPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDashboardHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 45C')
        ->toContain('Open Pay Code · campaign context · read-only')
        ->toContain('Open Distribution workspace · campaign context · read-only')
        ->toContain('Unsafe or mutating campaign attribution')
        ->toContain('Cockpit Wave 45D — Campaign Recipient Activity Detail / Distribution Publish / Browser Verification');

    expect($panel)
        ->toContain('campaign context · read-only')
        ->toContain('Open Distribution workspace');

    expect($frontendTest)
        ->toContain("not.toContain('read-only')")
        ->toContain("toContain('read-only')");

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 45C result: Campaign Recipient Activity Detail / Distribution UI hardening completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 45C — Campaign Recipient Activity Detail / Distribution UI Hardening');
});
