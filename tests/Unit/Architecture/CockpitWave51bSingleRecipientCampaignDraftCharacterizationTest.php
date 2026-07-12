<?php

declare(strict_types=1);

it('documents single recipient campaign draft characterization', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/319-wave-51b-single-recipient-campaign-draft-characterization.md');
    $unit = file_get_contents($packageRoot.'/tests/Unit/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapterTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 51B')
        ->toContain('Completed')
        ->toContain('template_key: ofw-remittance')
        ->toContain('does not issue a Pay Code');

    expect($unit)
        ->toContain('characterizes a single campaign recipient as a quick generate issuance draft without campaign mutation')
        ->toContain('plan-wave-51')
        ->toContain('BEN-WAVE-51')
        ->toContain('campaign_mutation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 51B result: Single Recipient Campaign Draft characterization completed')
        ->toContain('Cockpit Wave 51C — Campaign Draft Compiler Request Compatibility');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 51B — Single Recipient Campaign Draft Characterization')
        ->toContain('Cockpit Wave 51C — Campaign Draft Compiler Request Compatibility');
});
