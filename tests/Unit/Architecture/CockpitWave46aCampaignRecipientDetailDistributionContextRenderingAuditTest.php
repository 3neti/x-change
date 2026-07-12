<?php

declare(strict_types=1);

it('documents the campaign recipient detail distribution context rendering audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/293-wave-46a-campaign-recipient-detail-distribution-context-rendering-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 46A')
        ->toContain('Pay Code Detail and Distribution Workspace do not yet accept or render `campaign_navigation_context`')
        ->toContain('No campaign mutation')
        ->toContain('Wave 46B — Campaign Recipient Detail / Distribution Backend Prop Bridge');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 46A result: Campaign Recipient Detail / Distribution context rendering audit completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 46A — Campaign Recipient Detail / Distribution Context Rendering Audit');
});
