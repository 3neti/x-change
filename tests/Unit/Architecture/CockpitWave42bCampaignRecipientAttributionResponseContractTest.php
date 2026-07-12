<?php

declare(strict_types=1);

it('documents the campaign recipient attribution response contract', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/274-wave-42b-campaign-recipient-attribution-response-contract.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 42B')
        ->toContain('recipient_reference')
        ->toContain('campaign_recipient_id')
        ->toContain('Attribution remains read-only evidence')
        ->toContain('GeneratePayCode')
        ->toContain('Cockpit Wave 42C — Campaign Recipient Attribution UI Presentation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 42B result: Campaign Recipient Attribution response contract completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 42B — Campaign Recipient Attribution Response Contract');
});
