<?php

declare(strict_types=1);

it('documents the campaign recipient activity navigation ui hardening', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/285-wave-44c-campaign-recipient-activity-navigation-ui-hardening.md');
    $panel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitOperatorIssuanceActivityPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDashboardHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 44C')
        ->toContain('Open in Explorer · campaign context')
        ->toContain('Return to Campaign Dashboard · read-only')
        ->toContain('mutating campaign attribution')
        ->toContain('Cockpit Wave 44D — Campaign Recipient Activity Context Navigation Publish / Browser Verification');

    expect($panel)
        ->toContain('campaign context')
        ->toContain('read-only')
        ->toContain('mutatesCampaign');

    expect($frontendTest)
        ->toContain('does not propagate mutating campaign activity attribution into campaign navigation links')
        ->toContain('Campaign mutation: yes')
        ->toContain('Read-only: no');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 44C result: Campaign Recipient Activity navigation UI hardening completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 44C — Campaign Recipient Activity Navigation UI Hardening');
});
