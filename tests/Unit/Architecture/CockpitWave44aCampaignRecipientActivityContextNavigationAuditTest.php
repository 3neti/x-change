<?php

declare(strict_types=1);

it('documents the campaign recipient activity context navigation audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/283-wave-44a-campaign-recipient-activity-context-navigation-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 44A')
        ->toContain('Open in Explorer')
        ->toContain('campaign context')
        ->toContain('read-only context propagation')
        ->toContain('Cockpit Wave 44B — Campaign Recipient Activity Explorer Link Hydration');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 43 next recommended wave: `Cockpit Wave 44 — Campaign Recipient Activity Context Navigation / Explorer Bridge`')
        ->toContain('Cockpit Wave 44A result: Campaign Recipient Activity Context Navigation audit completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 44 — Campaign Recipient Activity Context Navigation / Explorer Bridge')
        ->toContain('Cockpit Wave 44A — Campaign Recipient Activity Context Navigation Audit');
});
