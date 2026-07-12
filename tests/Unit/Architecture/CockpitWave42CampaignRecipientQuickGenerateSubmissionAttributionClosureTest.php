<?php

declare(strict_types=1);

it('documents the campaign recipient quick generate submission attribution closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/277-wave-42-campaign-recipient-quick-generate-submission-attribution-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 42')
        ->toContain('recipient reference')
        ->toContain('campaign_recipient_id')
        ->toContain('No campaign mutation')
        ->toContain('GeneratePayCode')
        ->toContain('Cockpit Wave 43 — Campaign Recipient Issuance Activity Attribution / Dashboard Surfacing');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 42 completed: campaign-recipient Quick Generate submissions now preserve operator-safe recipient attribution after issuance');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 42 complete — Campaign Recipient Quick Generate Submission Attribution / Result Closure');
});
