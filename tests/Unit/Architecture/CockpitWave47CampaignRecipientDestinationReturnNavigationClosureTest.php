<?php

declare(strict_types=1);

it('documents campaign recipient destination return navigation closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/303-wave-47-campaign-recipient-destination-return-navigation-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 47')
        ->toContain('Completed')
        ->toContain('Return to Pay Code Detail · campaign context')
        ->toContain('No campaign mutation')
        ->toContain('Cockpit Wave 48 — Campaign Recipient Destination Context Copy / Operator Clarity');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 47 completed: campaign-aware Pay Code Detail and Distribution Workspace pages provide safe read-only return navigation');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 47 complete — Campaign Recipient Destination Return Navigation')
        ->toContain('Cockpit Wave 48 — Campaign Recipient Destination Context Copy / Operator Clarity');
});
