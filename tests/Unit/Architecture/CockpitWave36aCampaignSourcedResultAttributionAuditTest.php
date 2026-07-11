<?php

declare(strict_types=1);

it('records the campaign sourced result attribution audit scope', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/243-wave-36a-campaign-sourced-result-attribution-audit.md');

    expect($report)
        ->toContain('Cockpit Wave 36A')
        ->toContain('Wave 35 lets Quick Generate accept campaign context')
        ->toContain('campaign-aware return destinations')
        ->toContain('Preserve campaign attribution')
        ->toContain('existing Cockpit route query parameters')
        ->toContain('Keep `GeneratePayCode` as the issuance owner')
        ->toContain('Campaign mutation')
        ->toContain('Bulk issuance')
        ->toContain('Raw campaign, recipient, provider, wallet, or balance payload exposure')
        ->toContain('Cockpit Wave 36B — Campaign Attribution Response Contract / Backend Handoff Links');
});
