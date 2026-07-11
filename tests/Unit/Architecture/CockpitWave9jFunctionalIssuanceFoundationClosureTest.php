<?php

declare(strict_types=1);

it('documents cockpit wave 9j functional issuance foundation closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/150-wave-9j-functional-template-campaign-issuance-foundation-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 9J — Functional Template/Campaign Issuance Foundation Closure')
        ->and($report)->toContain('CockpitIssuanceDraftData')
        ->and($report)->toContain('CockpitIssuanceDraftCompilerContract')
        ->and($report)->toContain('CockpitIssuanceTemplateRegistryContract')
        ->and($report)->toContain('CockpitCampaignIssuanceDraftAdapterContract')
        ->and($report)->toContain('GeneratePayCodeRequest-compatible payload')
        ->and($report)->toContain('Next recommended wave: Cockpit Wave 10 — Runtime Compiler Adoption')
        ->and($cockpitCompass)->toContain('Cockpit Wave 9J — Functional Template/Campaign Issuance Foundation Closure')
        ->and($cockpitCompass)->toContain('reports/150-wave-9j-functional-template-campaign-issuance-foundation-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 9J — Functional Template/Campaign Issuance Foundation Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/150-wave-9j-functional-template-campaign-issuance-foundation-closure.md');
});
