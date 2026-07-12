<?php

declare(strict_types=1);

it('documents the campaign recipient destination acceptance intake audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/313-wave-50a-campaign-recipient-destination-acceptance-intake-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 50A')
        ->toContain('Completed')
        ->toContain('Automated browser evidence')
        ->toContain('Human acceptance record')
        ->toContain('Pending')
        ->toContain('No Cockpit mutation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 50A result: Campaign Recipient Destination acceptance intake audit completed')
        ->toContain('Cockpit Wave 50B — Pending Human Result Policy');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 50A — Campaign Recipient Destination Acceptance Intake Audit')
        ->toContain('Cockpit Wave 50B — Pending Human Result Policy');
});
