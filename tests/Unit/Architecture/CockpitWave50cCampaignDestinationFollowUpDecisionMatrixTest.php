<?php

declare(strict_types=1);

it('documents the campaign destination follow up decision matrix', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/315-wave-50c-campaign-destination-follow-up-decision-matrix.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 50C')
        ->toContain('Completed')
        ->toContain('Human acceptance remains `Pending` and automated evidence is green')
        ->toContain('Continue with non-mutating functional campaign work')
        ->toContain('Generate Pay Codes using template and campaign ideas');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 50C result: Campaign Destination follow-up decision matrix completed')
        ->toContain('Cockpit Wave 50D — Functional Campaign Issuance Return Plan');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 50C — Campaign Destination Follow-up Decision Matrix')
        ->toContain('Cockpit Wave 50D — Functional Campaign Issuance Return Plan');
});
