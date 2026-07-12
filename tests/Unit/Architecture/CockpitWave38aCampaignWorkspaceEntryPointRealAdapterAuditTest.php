<?php

declare(strict_types=1);

it('records the campaign workspace entry point real adapter audit scope', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/253-wave-38a-campaign-workspace-entry-point-real-adapter-audit.md');

    expect($report)
        ->toContain('Cockpit Wave 38A')
        ->toContain('CampaignCockpitWorkspace::summary')
        ->toContain('read-only')
        ->toContain('campaign_read_model.quick_generate_link')
        ->toContain('Prefer explicit dashboard query values over adapter metadata')
        ->toContain('Keep `GeneratePayCode` as the issuance owner')
        ->toContain('Campaign mutation')
        ->toContain('Bulk issuance')
        ->toContain('Raw campaign, recipient, provider, wallet, balance, import, or generation payload exposure')
        ->toContain('Cockpit Wave 38B — Campaign Adapter Source Context Normalization');
});
