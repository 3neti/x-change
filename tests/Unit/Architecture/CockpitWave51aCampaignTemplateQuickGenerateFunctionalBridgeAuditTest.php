<?php

declare(strict_types=1);

it('documents the campaign template quick generate functional bridge audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/318-wave-51a-campaign-template-quick-generate-functional-bridge-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 51A')
        ->toContain('Completed')
        ->toContain('DefaultCockpitCampaignIssuanceDraftAdapter')
        ->toContain('GeneratePayCodeRequest-compatible payload')
        ->toContain('Wave 51 should not create a parallel issuance runtime');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 51A result: Campaign Template Quick Generate functional bridge audit completed')
        ->toContain('Cockpit Wave 51B — Single Recipient Campaign Draft Characterization');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 51A — Campaign Template Quick Generate Functional Bridge Audit')
        ->toContain('Cockpit Wave 51B — Single Recipient Campaign Draft Characterization');
});
