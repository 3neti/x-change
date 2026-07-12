<?php

declare(strict_types=1);

it('closes the campaign plan to issuance draft template mapping wave', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/262-wave-39-campaign-plan-to-issuance-draft-template-mapping-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 39')
        ->toContain('Wave 39A')
        ->toContain('Wave 39B')
        ->toContain('Wave 39C')
        ->toContain('Wave 39D')
        ->toContain('money_changer')
        ->toContain('money-changer')
        ->toContain('ofw-remittance')
        ->toContain('Open Quick Generate')
        ->toContain('No campaign mutation')
        ->toContain('`GeneratePayCode` remains the issuance owner')
        ->toContain('Cockpit Wave 40 — Campaign Recipient-to-Issuance Draft Field Mapping');

    expect($cockpitCompass)
        ->toContain('reports/262-wave-39-campaign-plan-to-issuance-draft-template-mapping-closure.md')
        ->toContain('Cockpit Wave 40 — Campaign Recipient-to-Issuance Draft Field Mapping');

    expect($settlementCompass)
        ->toContain('../ui-cockpit/reports/262-wave-39-campaign-plan-to-issuance-draft-template-mapping-closure.md')
        ->toContain('Cockpit Wave 40 — Campaign Recipient-to-Issuance Draft Field Mapping');
});
