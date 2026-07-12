<?php

declare(strict_types=1);

it('documents the campaign recipient destination human acceptance record template', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/311-wave-49c-campaign-recipient-destination-human-acceptance-record-template.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 49C')
        ->toContain('Completed')
        ->toContain('Reviewer checklist')
        ->toContain('Result: pending')
        ->toContain('This record does not authorize mutation behavior');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 49C result: Campaign Recipient Destination human acceptance record template completed')
        ->toContain('Cockpit Wave 49D — Campaign Recipient Destination Manual Acceptance Closure');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 49C — Campaign Recipient Destination Human Acceptance Record Template')
        ->toContain('Cockpit Wave 49D — Campaign Recipient Destination Manual Acceptance Closure');
});
