<?php

declare(strict_types=1);

it('records the campaign context quick generate adoption scope', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/237-wave-35a-campaign-context-quick-generate-adoption-audit.md');

    expect($report)
        ->toContain('Cockpit Wave 35A')
        ->toContain('CockpitCampaignIssuanceDraftAdapterContract')
        ->toContain('metadata.campaign')
        ->toContain('Quick Generate currently does not accept campaign query context')
        ->toContain('Accept safe campaign query parameters')
        ->toContain('must not')
        ->toContain('mutate campaigns')
        ->toContain('perform bulk issuance')
        ->toContain('dispatch campaign feedback')
        ->toContain('bypass the existing `GeneratePayCode` handoff')
        ->toContain('Cockpit Wave 35B — Campaign Context Quick Generate Read Model Contract');
});
