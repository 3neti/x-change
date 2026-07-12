<?php

declare(strict_types=1);

it('documents the functional campaign issuance return plan', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/316-wave-50d-functional-campaign-issuance-return-plan.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 50D')
        ->toContain('Completed')
        ->toContain('Cockpit Wave 51 — Campaign Template Quick Generate Functional Bridge')
        ->toContain('GeneratePayCodeRequest')
        ->toContain('Keep `GeneratePayCode` as the issuance owner')
        ->toContain('No campaign mutation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 50D result: Functional Campaign Issuance return plan completed')
        ->toContain('Cockpit Wave 50E — Campaign Destination Acceptance Intake Closure');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 50D — Functional Campaign Issuance Return Plan')
        ->toContain('Cockpit Wave 50E — Campaign Destination Acceptance Intake Closure');
});
