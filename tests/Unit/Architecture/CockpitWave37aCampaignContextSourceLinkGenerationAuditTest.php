<?php

declare(strict_types=1);

it('records the campaign context source link generation audit scope', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/248-wave-37a-campaign-context-source-link-generation-audit.md');

    expect($report)
        ->toContain('Cockpit Wave 37A')
        ->toContain('Wave 35 lets `/x/cockpit/quick-generate` accept safe campaign query context')
        ->toContain('Wave 36 lets a campaign-prefilled Quick Generate submit return campaign attribution')
        ->toContain('full Quick Generate URL')
        ->toContain('campaign planning key, execution id, template, amount, recipient reference, and purpose')
        ->toContain('Keep campaign context read-only and prefill-only')
        ->toContain('Keep `GeneratePayCode` as the issuance owner')
        ->toContain('Campaign mutation')
        ->toContain('Bulk issuance')
        ->toContain('Raw campaign, recipient, provider, wallet, balance, or import payload exposure')
        ->toContain('Cockpit Wave 37B — Campaign Quick Generate Source Link Read Model Contract / Hydration');
});
