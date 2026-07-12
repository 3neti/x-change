<?php

declare(strict_types=1);

it('documents cockpit wave 54c pay code detail full url presentation', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/334-wave-54c-pay-code-detail-full-url-presentation.md';
    $pagePath = $packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue';
    $typesPath = $packageRoot.'/resources/js/cockpit/types.ts';
    $frontendTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $page = file_get_contents($pagePath);
    $types = file_get_contents($typesPath);
    $frontendTest = file_get_contents($frontendTestPath);

    expect($report)->toContain('Cockpit Wave 54C — Pay Code Detail Full URL Presentation')
        ->and($report)->toContain('Beneficiary Pay Code URL')
        ->and($report)->toContain('delivery disabled')
        ->and($report)->toContain('Cockpit Wave 54D — Distribution Workspace Full URL Presentation')
        ->and($page)->toContain('cockpit-voucher-detail-distribution-links-panel')
        ->and($page)->toContain('cockpit-voucher-detail-beneficiary-url-link')
        ->and($page)->toContain('Beneficiary Pay Code URL')
        ->and($page)->toContain('delivery disabled')
        ->and($types)->toContain('distribution_links?: Record<string, unknown>;')
        ->and($frontendTest)->toContain('renders the beneficiary Pay Code URL as a read-only distribution link')
        ->and($frontendTest)->toContain('https://example.test/x/claim/PC-HYDRATED-001/experience');
});
