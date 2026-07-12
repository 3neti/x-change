<?php

declare(strict_types=1);

it('closes the campaign recipient to issuance draft field mapping wave', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/267-wave-40-campaign-recipient-to-issuance-draft-field-mapping-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 40')
        ->toContain('Wave 40A')
        ->toContain('Wave 40B')
        ->toContain('Wave 40C')
        ->toContain('Wave 40D')
        ->toContain('recipient reference')
        ->toContain('Mobile and email remain off the source-link URL')
        ->toContain('Open Quick Generate')
        ->toContain('No campaign mutation')
        ->toContain('`GeneratePayCode` remains the issuance owner')
        ->toContain('Cockpit Wave 41 — Campaign Recipient Source-Link Selection / Operator Entry Point');

    expect($cockpitCompass)
        ->toContain('reports/267-wave-40-campaign-recipient-to-issuance-draft-field-mapping-closure.md')
        ->toContain('Cockpit Wave 41 — Campaign Recipient Source-Link Selection / Operator Entry Point');

    expect($settlementCompass)
        ->toContain('../ui-cockpit/reports/267-wave-40-campaign-recipient-to-issuance-draft-field-mapping-closure.md')
        ->toContain('Cockpit Wave 41 — Campaign Recipient Source-Link Selection / Operator Entry Point');
});
