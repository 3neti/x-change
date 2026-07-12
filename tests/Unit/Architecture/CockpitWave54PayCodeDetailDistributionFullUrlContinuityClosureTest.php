<?php

declare(strict_types=1);

it('documents and publishes cockpit wave 54 full url continuity closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/336-wave-54-pay-code-detail-distribution-full-url-continuity-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $hostVoucherDetail = file_get_contents($hostRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $hostDistributionWorkspace = file_get_contents($hostRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $hostTypes = file_get_contents($hostRoot.'/resources/js/cockpit/types.ts');

    expect($report)->toContain('Cockpit Wave 54 — Pay Code Detail / Distribution Full URL Continuity Closure')
        ->and($report)->toContain('checked: 58')
        ->and($report)->toContain('stale: 0')
        ->and($report)->toContain('Cockpit Wave 55 — Full URL Manual Distribution Operator Copy / Copy-to-Clipboard Decision')
        ->and($cockpitCompass)->toContain('Cockpit Wave 54 — Pay Code Detail / Distribution Full URL Continuity Closure')
        ->and($cockpitCompass)->toContain('reports/336-wave-54-pay-code-detail-distribution-full-url-continuity-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 54 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/336-wave-54-pay-code-detail-distribution-full-url-continuity-closure.md')
        ->and($hostVoucherDetail)->toContain('cockpit-voucher-detail-distribution-links-panel')
        ->and($hostVoucherDetail)->toContain('cockpit-voucher-detail-beneficiary-url-link')
        ->and($hostDistributionWorkspace)->toContain('cockpit-distribution-workspace-links-panel')
        ->and($hostDistributionWorkspace)->toContain('cockpit-distribution-workspace-beneficiary-url-link')
        ->and($hostTypes)->toContain('distribution_links?: Record<string, unknown>;');
});
