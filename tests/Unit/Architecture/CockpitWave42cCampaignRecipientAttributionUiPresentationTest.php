<?php

declare(strict_types=1);

it('documents the campaign recipient attribution ui presentation', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/275-wave-42c-campaign-recipient-attribution-ui-presentation.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 42C')
        ->toContain('recipient id')
        ->toContain('recipient reference')
        ->toContain('No campaign mutation button was added')
        ->toContain('Cockpit Wave 42D — Campaign Recipient Attribution Publish / Browser Verification');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 42C result: Campaign Recipient Attribution UI presentation completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 42C — Campaign Recipient Attribution UI Presentation');
});
