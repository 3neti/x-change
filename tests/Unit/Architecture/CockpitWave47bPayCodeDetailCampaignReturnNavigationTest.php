<?php

declare(strict_types=1);

it('documents the pay code detail campaign return navigation', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/300-wave-47b-pay-code-detail-campaign-return-navigation.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $frontend = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 47B')
        ->toContain('Return to Explorer · campaign context')
        ->toContain('Return to Campaign Dashboard · read-only')
        ->toContain('Cockpit Wave 47C — Distribution Workspace Campaign Return Navigation');

    expect($page)
        ->toContain('cockpit-voucher-detail-campaign-explorer-return-link')
        ->toContain('cockpit-voucher-detail-campaign-dashboard-return-link')
        ->toContain('campaignQueryString');

    expect($frontend)
        ->toContain('campaign_recipient_id=recipient-wave-46')
        ->toContain('activity_source=operator_issuance_activity')
        ->toContain('cockpit-voucher-detail-campaign-dashboard-return-link');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 47B result: Pay Code Detail campaign return navigation completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 47B — Pay Code Detail Campaign Return Navigation');
});
