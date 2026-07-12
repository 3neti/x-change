<?php

declare(strict_types=1);

it('documents the campaign recipient to issuance draft field mapping audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/263-wave-40a-campaign-recipient-to-issuance-draft-field-mapping-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 40A')
        ->toContain('recipient_reference')
        ->toContain('feedback `mobile`')
        ->toContain('CockpitCampaignIssuanceDraftAdapterContract')
        ->toContain('GeneratePayCode')
        ->toContain('no campaign mutation')
        ->toContain('Cockpit Wave 40B — Campaign Recipient Field Normalizer / Draft Adapter');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 39 next recommended wave: `Cockpit Wave 40 — Campaign Recipient-to-Issuance Draft Field Mapping`');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 40 — Campaign Recipient-to-Issuance Draft Field Mapping');
});
