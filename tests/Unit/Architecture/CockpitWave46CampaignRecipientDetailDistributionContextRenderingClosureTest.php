<?php

declare(strict_types=1);

it('documents the campaign recipient detail distribution context rendering closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/298-wave-46-campaign-recipient-detail-distribution-context-rendering-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 46')
        ->toContain('Completed')
        ->toContain('destination `pay_code_detail`')
        ->toContain('destination `distribution_workspace`')
        ->toContain('No campaign mutation')
        ->toContain('Cockpit Wave 47 — Campaign Recipient Destination Return Navigation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 46 completed: Pay Code Detail and Distribution Workspace render safe campaign-recipient context cards');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 46 complete — Campaign Recipient Detail / Distribution Context Rendering')
        ->toContain('Cockpit Wave 47 — Campaign Recipient Destination Return Navigation');
});
