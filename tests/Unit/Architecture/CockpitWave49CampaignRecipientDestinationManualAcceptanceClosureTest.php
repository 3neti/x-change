<?php

declare(strict_types=1);

it('documents campaign recipient destination manual acceptance checkpoint closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/312-wave-49-campaign-recipient-destination-manual-acceptance-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 49')
        ->toContain('Completed as a scaffolded checkpoint')
        ->toContain('Automated browser evidence is green')
        ->toContain('Result: pending')
        ->toContain('Cockpit Wave 50 — Campaign Recipient Destination Acceptance Intake / Follow-up Decision');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 49 completed: campaign-aware destination acceptance checkpoint is documented')
        ->toContain('Cockpit Wave 50 — Campaign Recipient Destination Acceptance Intake / Follow-up Decision');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 49 complete — Campaign Recipient Destination Manual Acceptance Checkpoint')
        ->toContain('Cockpit Wave 50 — Campaign Recipient Destination Acceptance Intake / Follow-up Decision');
});
