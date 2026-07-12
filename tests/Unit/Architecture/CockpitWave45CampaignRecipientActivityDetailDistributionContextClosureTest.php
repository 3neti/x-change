<?php

declare(strict_types=1);

it('documents the campaign recipient activity detail distribution context bridge closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/292-wave-45-campaign-recipient-activity-detail-distribution-context-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 45')
        ->toContain('Completed')
        ->toContain('Open Pay Code · campaign context · read-only')
        ->toContain('Open Distribution workspace · campaign context · read-only')
        ->toContain('Campaign context is not propagated when attribution is mutating or not read-only')
        ->toContain('Cockpit Wave 46 — Campaign Recipient Detail / Distribution Context Rendering');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 45 completed: dashboard Operator Issuance Activity cards can preserve safe campaign-recipient context in read-only Pay Code Detail, Distribution Workspace, and Explorer navigation links');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 45 complete — Campaign Recipient Activity Detail / Distribution Context Bridge')
        ->toContain('Cockpit Wave 46 — Campaign Recipient Detail / Distribution Context Rendering');
});
