<?php

declare(strict_types=1);

it('documents the campaign recipient detail distribution backend prop bridge', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/294-wave-46b-campaign-recipient-detail-distribution-backend-prop-bridge.md');
    $props = file_get_contents($packageRoot.'/src/Support/Cockpit/CockpitReadOnlyPageProps.php');
    $routes = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 46B')
        ->toContain('pay_code_detail')
        ->toContain('distribution_workspace')
        ->toContain('x_campaign_adapter')
        ->toContain('Cockpit Wave 46C — Campaign Recipient Detail Context Rendering');

    expect($props)
        ->toContain('toVoucherDetailArray')
        ->toContain('toDistributionWorkspaceArray')
        ->toContain("'x_campaign_adapter'")
        ->toContain("'campaign_navigation_context'");

    expect($routes)
        ->toContain('passes optional campaign recipient navigation context to voucher detail and distribution destinations')
        ->toContain('recipient-wave-46')
        ->toContain('assertJsonMissingPath');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 46B result: Campaign Recipient Detail / Distribution backend prop bridge completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 46B — Campaign Recipient Detail / Distribution Backend Prop Bridge');
});
