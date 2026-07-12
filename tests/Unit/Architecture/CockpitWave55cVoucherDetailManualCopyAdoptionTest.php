<?php

declare(strict_types=1);

it('documents cockpit wave 55c voucher detail manual copy adoption', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/339-wave-55c-voucher-detail-manual-copy-adoption.md';
    $pagePath = $packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue';
    $frontendTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $page = file_get_contents($pagePath);
    $frontendTest = file_get_contents($frontendTestPath);

    expect($report)->toContain('Cockpit Wave 55C — Voucher Detail Manual Copy Adoption')
        ->and($report)->toContain('does not call `fetch`')
        ->and($report)->toContain('Cockpit Wave 55D — Distribution Workspace Manual Copy Adoption')
        ->and($page)->toContain('CockpitManualCopyButton')
        ->and($page)->toContain('Copy beneficiary URL')
        ->and($frontendTest)->toContain('copies the Voucher Detail beneficiary URL through the browser clipboard only')
        ->and($frontendTest)->toContain('not.toHaveBeenCalled');
});
