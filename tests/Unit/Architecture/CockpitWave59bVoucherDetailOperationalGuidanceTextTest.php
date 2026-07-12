<?php

declare(strict_types=1);

it('documents cockpit wave 59b voucher detail operational guidance text', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/354-wave-59b-voucher-detail-operational-guidance-text.md';
    $pagePath = $packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue';
    $frontendTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $page = file_get_contents($pagePath);
    $frontendTest = file_get_contents($frontendTestPath);

    expect($report)->toContain('Cockpit Wave 59B — Voucher Detail Operational Guidance Text')
        ->and($report)->toContain('manual distribution only')
        ->and($report)->toContain('approved external workflow')
        ->and($report)->toContain('sensitive settlement access material')
        ->and($report)->toContain('Cockpit Wave 59C — Distribution Workspace Operational Guidance Text')
        ->and($page)->toContain('cockpit-voucher-detail-manual-distribution-guidance')
        ->and($page)->toContain('Manual distribution guidance')
        ->and($frontendTest)->toContain('renders Voucher Detail manual distribution operational guidance');
});
