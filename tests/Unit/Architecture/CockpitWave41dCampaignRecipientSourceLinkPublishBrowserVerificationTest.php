<?php

declare(strict_types=1);

it('documents the campaign recipient source link publish and browser verification', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/271-wave-41d-campaign-recipient-source-link-publish-browser-verification.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 41D')
        ->toContain('x-change:install --force')
        ->toContain('checked: 58')
        ->toContain('Browser smoke: passed')
        ->toContain('Recipient Quick Generate entry points')
        ->toContain('Cockpit Wave 41E — Campaign Recipient Source-Link Selection Closure');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 41D result: Campaign Recipient Source-Link publish/browser verification completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 41D — Campaign Recipient Source-Link Publish / Browser Verification');
});
