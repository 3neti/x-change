<?php

declare(strict_types=1);

it('documents campaign recipient destination context copy closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/308-wave-48-campaign-recipient-destination-context-copy-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 48')
        ->toContain('Completed')
        ->toContain('Opened from campaign activity')
        ->toContain('Inspecting distribution from campaign activity')
        ->toContain('Cockpit Wave 49 — Campaign Recipient Destination Manual Acceptance Checkpoint');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 48 completed: campaign-aware destination pages now use clearer operator copy')
        ->toContain('Cockpit Wave 49 — Campaign Recipient Destination Manual Acceptance Checkpoint');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 48 complete — Campaign Recipient Destination Context Copy / Operator Clarity')
        ->toContain('Cockpit Wave 49 — Campaign Recipient Destination Manual Acceptance Checkpoint');
});
