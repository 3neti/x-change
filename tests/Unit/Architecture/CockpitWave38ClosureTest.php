<?php

declare(strict_types=1);

it('closes the campaign workspace entry point real adapter adoption wave', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/257-wave-38-campaign-workspace-entry-point-real-adapter-adoption-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 38')
        ->toContain('Wave 38A')
        ->toContain('Wave 38B')
        ->toContain('Wave 38C')
        ->toContain('Wave 38D')
        ->toContain('campaign_read_model.quick_generate_link')
        ->toContain('CampaignCockpitSummaryData')
        ->toContain('Open Quick Generate')
        ->toContain('Campaign context prefill')
        ->toContain('No campaign mutation')
        ->toContain('`GeneratePayCode` remains the issuance owner')
        ->toContain('Cockpit Wave 39 — Campaign Plan-to-Issuance Draft Template Mapping');

    expect($cockpitCompass)
        ->toContain('reports/257-wave-38-campaign-workspace-entry-point-real-adapter-adoption-closure.md')
        ->toContain('Cockpit Wave 39 — Campaign Plan-to-Issuance Draft Template Mapping');

    expect($settlementCompass)
        ->toContain('../ui-cockpit/reports/257-wave-38-campaign-workspace-entry-point-real-adapter-adoption-closure.md')
        ->toContain('Cockpit Wave 39 — Campaign Plan-to-Issuance Draft Template Mapping');
});
