<?php

declare(strict_types=1);

it('documents the campaign recipient activity context navigation closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/287-wave-44-campaign-recipient-activity-context-navigation-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 44')
        ->toContain('Campaign Recipient Activity Context Navigation / Explorer Bridge')
        ->toContain('Open in Explorer · campaign context')
        ->toContain('Return to Campaign Dashboard · read-only')
        ->toContain('No campaign mutation')
        ->toContain('Cockpit Wave 45 — Campaign Recipient Activity Detail / Distribution Context Bridge');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 44 completed: dashboard Operator Issuance Activity cards can preserve safe campaign-recipient context in read-only Explorer and Campaign Dashboard navigation links');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 44 complete — Campaign Recipient Activity Context Navigation / Explorer Bridge')
        ->toContain('Cockpit Wave 45 — Campaign Recipient Activity Detail / Distribution Context Bridge');
});
