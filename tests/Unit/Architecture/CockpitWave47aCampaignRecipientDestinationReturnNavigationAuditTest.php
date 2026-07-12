<?php

declare(strict_types=1);

it('documents the campaign recipient destination return navigation audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/299-wave-47a-campaign-recipient-destination-return-navigation-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 47A')
        ->toContain('return to Campaign-aware Explorer')
        ->toContain('campaign_recipient_id')
        ->toContain('No campaign mutation')
        ->toContain('Wave 47B — Pay Code Detail Campaign Return Navigation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 47A result: Campaign Recipient Destination Return Navigation audit completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 47A — Campaign Recipient Destination Return Navigation Audit');
});
