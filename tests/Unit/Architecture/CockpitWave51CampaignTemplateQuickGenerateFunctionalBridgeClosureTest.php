<?php

declare(strict_types=1);

it('documents campaign template quick generate functional bridge closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/322-wave-51-campaign-template-quick-generate-functional-bridge-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 51')
        ->toContain('Completed')
        ->toContain('GeneratePayCodeRequest-compatible payload')
        ->toContain('recipient mobile is carried into both feedback and validation')
        ->toContain('DefaultCockpitCampaignIssuanceDraftAdapter')
        ->toContain('Cockpit Wave 52 — Campaign Template Quick Generate Runtime Adoption Decision');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 51 completed: campaign/template Quick Generate functional bridge characterized and protected')
        ->toContain('Cockpit Wave 52 — Campaign Template Quick Generate Runtime Adoption Decision');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 51 complete — Campaign Template Quick Generate Functional Bridge')
        ->toContain('Cockpit Wave 52 — Campaign Template Quick Generate Runtime Adoption Decision');
});
