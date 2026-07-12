<?php

declare(strict_types=1);

it('closes the campaign context source link generation wave', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/252-wave-37-campaign-context-source-link-generation-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 37')
        ->toContain('Wave 37A')
        ->toContain('Wave 37B')
        ->toContain('Wave 37C')
        ->toContain('Wave 37D')
        ->toContain('campaign_read_model.quick_generate_link')
        ->toContain('Open Quick Generate')
        ->toContain('Campaign context prefill')
        ->toContain('No campaign mutation')
        ->toContain('`GeneratePayCode` remains the issuance owner')
        ->toContain('Cockpit Wave 38 — Campaign Workspace Entry Point Real Adapter / x-campaign Source Context Adoption');

    expect($cockpitCompass)
        ->toContain('reports/252-wave-37-campaign-context-source-link-generation-closure.md')
        ->toContain('Cockpit Wave 38 — Campaign Workspace Entry Point Real Adapter / x-campaign Source Context Adoption');

    expect($settlementCompass)
        ->toContain('../ui-cockpit/reports/252-wave-37-campaign-context-source-link-generation-closure.md')
        ->toContain('Cockpit Wave 38 — Campaign Workspace Entry Point Real Adapter / x-campaign Source Context Adoption');
});
