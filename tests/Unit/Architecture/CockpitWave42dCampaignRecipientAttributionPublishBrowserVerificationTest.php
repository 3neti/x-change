<?php

declare(strict_types=1);

it('documents the campaign recipient attribution publish and browser verification', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/276-wave-42d-campaign-recipient-attribution-publish-browser-verification.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 42D')
        ->toContain('checked: 58')
        ->toContain('Browser smoke: passed')
        ->toContain('campaign_recipient_id')
        ->toContain('recipient reference')
        ->toContain('Cockpit Wave 42E — Campaign Recipient Quick Generate Submission Attribution Closure');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 42D result: Campaign Recipient Attribution publish/browser verification completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 42D — Campaign Recipient Attribution Publish / Browser Verification');
});
