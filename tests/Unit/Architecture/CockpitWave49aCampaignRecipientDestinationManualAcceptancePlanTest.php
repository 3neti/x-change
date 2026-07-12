<?php

declare(strict_types=1);

it('documents the campaign recipient destination manual acceptance plan', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/309-wave-49a-campaign-recipient-destination-manual-acceptance-plan.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 49A')
        ->toContain('Completed')
        ->toContain('Dashboard activity card')
        ->toContain('Pay Code Detail')
        ->toContain('Distribution Workspace')
        ->toContain('This checkpoint does not authorize new Cockpit mutations');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 49A result: Campaign Recipient Destination Manual Acceptance plan completed')
        ->toContain('Cockpit Wave 49B — Campaign Recipient Destination Automated Evidence Check');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 49A — Campaign Recipient Destination Manual Acceptance Plan')
        ->toContain('Cockpit Wave 49B — Campaign Recipient Destination Automated Evidence Check');
});
