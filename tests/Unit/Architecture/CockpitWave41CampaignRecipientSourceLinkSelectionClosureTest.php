<?php

declare(strict_types=1);

it('documents the campaign recipient source link selection closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/272-wave-41-campaign-recipient-source-link-selection-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 41')
        ->toContain('campaign_read_model.recipient_quick_generate_links')
        ->toContain('Recipient Quick Generate entry points')
        ->toContain('does not mutate campaign state')
        ->toContain('Existing Campaign `Open Quick Generate` Playwright smoke passed')
        ->toContain('Cockpit Wave 42 — Campaign Recipient Quick Generate Submission Attribution / Result Closure');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 41 completed: campaign adapters can expose safe recipient-level Quick Generate entry points');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 41 complete — Campaign Recipient Source-Link Selection / Operator Entry Point');
});
