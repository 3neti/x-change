<?php

declare(strict_types=1);

it('documents the campaign recipient activity dashboard presentation', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/280-wave-43c-campaign-recipient-activity-dashboard-presentation.md');
    $presenter = file_get_contents($packageRoot.'/src/Services/Cockpit/DefaultCockpitOperatorIssuanceActivityPresenter.php');
    $provider = file_get_contents($packageRoot.'/src/Services/Cockpit/DurableCockpitOperatorIssuanceActivityReadModelProvider.php');
    $panel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitOperatorIssuanceActivityPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDashboardHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 43C')
        ->toContain('Campaign attribution')
        ->toContain('Campaign mutation: no')
        ->toContain('Read-only: yes')
        ->toContain('Cockpit Wave 43D — Campaign Recipient Activity Publish / Browser Verification');

    expect($presenter)
        ->toContain('safeCampaignAttribution')
        ->toContain('campaign_attribution');

    expect($provider)
        ->toContain('safeCampaignAttributionMetadata')
        ->toContain('campaign_attribution');

    expect($panel)
        ->toContain('cockpit-operator-issuance-activity-campaign-attribution')
        ->toContain('Campaign mutation');

    expect($frontendTest)
        ->toContain('Campaign: campaign-wave-43c')
        ->toContain('Recipient reference: 09173011987')
        ->toContain('must-not-render');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 43C result: Campaign Recipient Activity dashboard presentation completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 43C — Campaign Recipient Activity Dashboard Presentation');
});
