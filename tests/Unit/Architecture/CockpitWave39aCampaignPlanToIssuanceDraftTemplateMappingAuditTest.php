<?php

declare(strict_types=1);

it('documents the campaign plan to issuance draft template mapping audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/258-wave-39a-campaign-plan-to-issuance-draft-template-mapping-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 39A')
        ->toContain('Campaign Plan-to-Issuance Draft Template Mapping Audit')
        ->toContain('CockpitCampaignIssuanceDraftAdapterContract')
        ->toContain('CockpitIssuanceDraftData')
        ->toContain('GeneratePayCode')
        ->toContain('no campaign mutation')
        ->toContain('Cockpit Wave 39B — Campaign Template Intent Normalizer / Draft Adapter');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 38 next recommended wave: `Cockpit Wave 39 — Campaign Plan-to-Issuance Draft Template Mapping`');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 39 — Campaign Plan-to-Issuance Draft Template Mapping');
});
