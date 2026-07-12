<?php

declare(strict_types=1);

it('documents the campaign recipient source link read model contract', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/269-wave-41b-campaign-recipient-source-link-read-model-contract.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 41B')
        ->toContain('campaign_read_model.recipient_quick_generate_links')
        ->toContain('metadata.recipient_quick_generate_contexts')
        ->toContain('GeneratePayCode')
        ->toContain('do not mutate campaign state')
        ->toContain('Cockpit Wave 41C — Campaign Recipient Source-Link UI Presentation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 41B result: Campaign Recipient Source-Link read-model contract and hydration completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 41B — Campaign Recipient Source-Link Read Model Contract / Hydration');
});
