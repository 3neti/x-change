<?php

declare(strict_types=1);

it('closes the campaign sourced result attribution wave', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/247-wave-36-campaign-sourced-result-attribution-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 36')
        ->toContain('Wave 36A')
        ->toContain('Wave 36B')
        ->toContain('Wave 36C')
        ->toContain('Wave 36D')
        ->toContain('returns safe `campaign_attribution`')
        ->toContain('Return to Campaign Explorer')
        ->toContain('Return to Campaign Dashboard')
        ->toContain('GeneratePayCode` remains the issuance owner')
        ->toContain('Cockpit Wave 37 — Campaign Context Source Link Generation / Campaign Surface Entry Points');

    expect($cockpitCompass)
        ->toContain('reports/247-wave-36-campaign-sourced-result-attribution-closure.md')
        ->toContain('Cockpit Wave 37 — Campaign Context Source Link Generation / Campaign Surface Entry Points');

    expect($settlementCompass)
        ->toContain('../ui-cockpit/reports/247-wave-36-campaign-sourced-result-attribution-closure.md')
        ->toContain('Cockpit Wave 37 — Campaign Context Source Link Generation / Campaign Surface Entry Points');
});
