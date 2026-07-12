<?php

declare(strict_types=1);

it('documents the pay code detail campaign context copy refinement', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/305-wave-48b-pay-code-detail-context-copy-refinement.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $frontend = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 48B')
        ->toContain('Completed')
        ->toContain('Back to Explorer · read-only')
        ->toContain('No campaign mutation');

    expect($page)
        ->toContain('Opened from campaign activity')
        ->toContain('only change the read-only Cockpit view')
        ->toContain('Back to Campaign Dashboard · read-only');

    expect($frontend)
        ->toContain('Opened from campaign activity')
        ->toContain('Back to Explorer')
        ->toContain('campaign_recipient_id=recipient-wave-46');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 48B result: Pay Code Detail context copy refinement completed')
        ->toContain('Cockpit Wave 48C — Distribution Workspace Context Copy Refinement');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 48B — Pay Code Detail Context Copy Refinement')
        ->toContain('Cockpit Wave 48C — Distribution Workspace Context Copy Refinement');
});
