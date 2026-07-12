<?php

declare(strict_types=1);

it('records the campaign recipient destination context copy audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/304-wave-48a-campaign-recipient-destination-context-copy-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 48A')
        ->toContain('Completed')
        ->toContain('Opened from campaign activity')
        ->toContain('Inspecting distribution from campaign activity')
        ->toContain('No mutation behavior is authorized in Wave 48');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 48A result: Campaign Recipient Destination Context Copy audit completed')
        ->toContain('Cockpit Wave 48B — Pay Code Detail Context Copy Refinement');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 48A — Campaign Recipient Destination Context Copy Audit')
        ->toContain('Cockpit Wave 48B — Pay Code Detail Context Copy Refinement');
});
