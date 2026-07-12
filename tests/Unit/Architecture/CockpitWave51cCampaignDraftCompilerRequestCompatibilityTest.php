<?php

declare(strict_types=1);

it('documents campaign draft compiler request compatibility', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/320-wave-51c-campaign-draft-compiler-request-compatibility.md');
    $unit = file_get_contents($packageRoot.'/tests/Unit/Cockpit/DefaultCockpitIssuanceDraftCompilerTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 51C')
        ->toContain('Completed')
        ->toContain('GeneratePayCodeRequest')
        ->toContain('does not issue a Pay Code');

    expect($unit)
        ->toContain('compiles a single campaign recipient draft into a GeneratePayCodeRequest compatible payload')
        ->toContain('new Factory')
        ->toContain('plan-wave-51')
        ->toContain('campaign_mutation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 51C result: Campaign Draft Compiler request compatibility completed')
        ->toContain('Cockpit Wave 51D — Functional Bridge Safety Boundary');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 51C — Campaign Draft Compiler Request Compatibility')
        ->toContain('Cockpit Wave 51D — Functional Bridge Safety Boundary');
});
