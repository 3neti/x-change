<?php

declare(strict_types=1);

it('documents cockpit wave 59 manual distribution link operational guidance closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/356-wave-59-manual-distribution-link-operational-guidance-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $hostVoucherDetail = file_get_contents($hostRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $hostDistributionWorkspace = file_get_contents($hostRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');

    expect($report)->toContain('Cockpit Wave 59 — Manual Distribution Link Operational Guidance Closure')
        ->and($report)->toContain('manual distribution only')
        ->and($report)->toContain('approved external workflow')
        ->and($report)->toContain('sensitive settlement access material')
        ->and($report)->toContain('checked: 59')
        ->and($report)->toContain('Cockpit Wave 60 — Manual Distribution Link Human Guidance Acceptance')
        ->and($cockpitCompass)->toContain('Cockpit Wave 59 — Manual Distribution Link Operational Guidance Closure')
        ->and($cockpitCompass)->toContain('reports/356-wave-59-manual-distribution-link-operational-guidance-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 59 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/356-wave-59-manual-distribution-link-operational-guidance-closure.md')
        ->and($hostVoucherDetail)->toContain('cockpit-voucher-detail-manual-distribution-guidance')
        ->and($hostDistributionWorkspace)->toContain('cockpit-distribution-workspace-manual-distribution-guidance');
});
