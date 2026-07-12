<?php

declare(strict_types=1);

it('documents the campaign recipient destination automated evidence check', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/310-wave-49b-campaign-recipient-destination-automated-evidence-check.md');
    $playwright = file_get_contents($hostRoot.'/tests/playwright/cockpit-campaign-activity-navigation.spec.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 49B')
        ->toContain('Completed')
        ->toContain('Playwright: 1 passed')
        ->toContain('Opened from campaign activity')
        ->toContain('Inspecting distribution from campaign activity');

    expect($playwright)
        ->toContain('Opened from campaign activity')
        ->toContain('Inspecting distribution from campaign activity')
        ->toContain('Back to Explorer')
        ->toContain('Back to Pay Code Detail');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 49B result: Campaign Recipient Destination automated evidence check completed')
        ->toContain('Cockpit Wave 49C — Campaign Recipient Destination Human Acceptance Record Template');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 49B — Campaign Recipient Destination Automated Evidence Check')
        ->toContain('Cockpit Wave 49C — Campaign Recipient Destination Human Acceptance Record Template');
});
