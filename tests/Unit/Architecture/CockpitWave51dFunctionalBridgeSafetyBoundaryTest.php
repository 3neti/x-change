<?php

declare(strict_types=1);

it('documents and protects the functional bridge safety boundary', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/321-wave-51d-functional-bridge-safety-boundary.md');
    $adapter = file_get_contents($packageRoot.'/src/Services/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapter.php');
    $compiler = file_get_contents($packageRoot.'/src/Services/Cockpit/DefaultCockpitIssuanceDraftCompiler.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 51D')
        ->toContain('Completed')
        ->toContain('preparation bridge only')
        ->toContain('direct calls to `GeneratePayCode`')
        ->toContain('must not create a parallel campaign issuance runtime');

    expect($adapter)
        ->not->toContain('GeneratePayCode')
        ->not->toContain('Journal')
        ->not->toContain('PayoutProvider')
        ->not->toContain('Wallet');

    expect($compiler)
        ->not->toContain('GeneratePayCode')
        ->not->toContain('Journal')
        ->not->toContain('PayoutProvider')
        ->not->toContain('Wallet');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 51D result: Functional Bridge safety boundary completed')
        ->toContain('Cockpit Wave 51E — Campaign Template Quick Generate Functional Bridge Closure');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 51D — Functional Bridge Safety Boundary')
        ->toContain('Cockpit Wave 51E — Campaign Template Quick Generate Functional Bridge Closure');
});
