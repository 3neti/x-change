<?php

declare(strict_types=1);

it('documents the campaign recipient issuance activity attribution closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/282-wave-43-campaign-recipient-issuance-activity-attribution-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 43')
        ->toContain('Campaign Recipient Issuance Activity Attribution / Dashboard Surfacing')
        ->toContain('Campaign attribution')
        ->toContain('Campaign mutation: no')
        ->toContain('No campaign mutation')
        ->toContain('GeneratePayCode')
        ->toContain('Cockpit Wave 44 — Campaign Recipient Activity Context Navigation / Explorer Bridge');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 43 completed: campaign-recipient Quick Generate activity now carries operator-safe campaign attribution into durable activity metadata and can surface it on the Cockpit dashboard');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 43 complete — Campaign Recipient Issuance Activity Attribution / Dashboard Surfacing')
        ->toContain('Cockpit Wave 44 — Campaign Recipient Activity Context Navigation / Explorer Bridge');
});
