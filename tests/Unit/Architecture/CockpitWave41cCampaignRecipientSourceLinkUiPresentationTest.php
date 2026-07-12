<?php

declare(strict_types=1);

it('documents the campaign recipient source link ui presentation', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/270-wave-41c-campaign-recipient-source-link-ui-presentation.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 41C')
        ->toContain('Recipient Quick Generate entry points')
        ->toContain('recipient_quick_generate_links')
        ->toContain('No campaign mutation button was added')
        ->toContain('Cockpit Wave 41D — Campaign Recipient Source-Link Publish / Browser Verification');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 41C result: Campaign Recipient Source-Link UI presentation completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 41C — Campaign Recipient Source-Link UI Presentation');
});
