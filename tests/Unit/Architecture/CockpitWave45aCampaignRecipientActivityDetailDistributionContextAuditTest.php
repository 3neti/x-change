<?php

declare(strict_types=1);

it('documents the campaign recipient activity detail distribution context audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/288-wave-45a-campaign-recipient-activity-detail-distribution-context-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 45A')
        ->toContain('Pay Code detail')
        ->toContain('Distribution workspace')
        ->toContain('read-only context propagation')
        ->toContain('Cockpit Wave 45B — Campaign Recipient Activity Detail / Distribution Link Hydration');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 44 next recommended wave: `Cockpit Wave 45 — Campaign Recipient Activity Detail / Distribution Context Bridge`')
        ->toContain('Cockpit Wave 45A result: Campaign Recipient Activity Detail / Distribution Context audit completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 45 — Campaign Recipient Activity Detail / Distribution Context Bridge')
        ->toContain('Cockpit Wave 45A — Campaign Recipient Activity Detail / Distribution Context Audit');
});
