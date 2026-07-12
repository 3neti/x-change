<?php

declare(strict_types=1);

it('documents the campaign recipient source link selection audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/268-wave-41a-campaign-recipient-source-link-selection-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 41A')
        ->toContain('recipient-level operator entry points')
        ->toContain('campaign_read_model.quick_generate_link')
        ->toContain('GeneratePayCode')
        ->toContain('campaign mutation')
        ->toContain('Cockpit Wave 41B — Campaign Recipient Source-Link Read Model Contract / Hydration');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 40 next recommended wave: `Cockpit Wave 41 — Campaign Recipient Source-Link Selection / Operator Entry Point`')
        ->toContain('Cockpit Wave 41A result: Campaign Recipient Source-Link Selection audit completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 41 — Campaign Recipient Source-Link Selection / Operator Entry Point')
        ->toContain('Cockpit Wave 41A — Campaign Recipient Source-Link Selection Audit');
});
