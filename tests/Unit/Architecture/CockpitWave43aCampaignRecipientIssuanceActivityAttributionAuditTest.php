<?php

declare(strict_types=1);

it('documents the campaign recipient issuance activity attribution audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/278-wave-43a-campaign-recipient-issuance-activity-attribution-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 43A')
        ->toContain('durable operator issuance activity')
        ->toContain('campaign-recipient attribution')
        ->toContain('read-only dashboard evidence')
        ->toContain('GeneratePayCode')
        ->toContain('Cockpit Wave 43B — Campaign Recipient Activity Metadata Handoff');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 42 next recommended wave: `Cockpit Wave 43 — Campaign Recipient Issuance Activity Attribution / Dashboard Surfacing`')
        ->toContain('Cockpit Wave 43A result: Campaign Recipient Issuance Activity Attribution audit completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 43 — Campaign Recipient Issuance Activity Attribution / Dashboard Surfacing')
        ->toContain('Cockpit Wave 43A — Campaign Recipient Issuance Activity Attribution Audit');
});
