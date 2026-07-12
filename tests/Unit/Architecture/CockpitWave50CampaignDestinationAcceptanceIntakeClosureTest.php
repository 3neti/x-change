<?php

declare(strict_types=1);

it('documents campaign destination acceptance intake closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/317-wave-50-campaign-destination-acceptance-intake-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 50')
        ->toContain('Completed')
        ->toContain('Continue with non-mutating functional campaign work')
        ->toContain('Cockpit Wave 51 — Campaign Template Quick Generate Functional Bridge')
        ->toContain('GeneratePayCodeRequest')
        ->toContain('No campaign mutation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 50 completed: acceptance intake closed with a decision to continue non-mutating functional campaign work')
        ->toContain('Cockpit Wave 51 — Campaign Template Quick Generate Functional Bridge');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 50 complete — Campaign Recipient Destination Acceptance Intake / Follow-up Decision')
        ->toContain('Cockpit Wave 51 — Campaign Template Quick Generate Functional Bridge');
});
