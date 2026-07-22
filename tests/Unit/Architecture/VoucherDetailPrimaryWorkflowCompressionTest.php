<?php

declare(strict_types=1);

it('documents voucher detail primary workflow compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/628-voucher-detail-primary-workflow-compression-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Voucher Detail Primary Workflow Compression — Slice 1')
        ->toContain('sleek operational header')
        ->toContain('Presentation-only shell compression')
        ->and($page)->toContain('data-testid="cockpit-voucher-detail-header"')
        ->and($page)->toContain('data-testid="cockpit-voucher-detail-header-facts"')
        ->and($page)->toContain('data-testid="cockpit-voucher-detail-boundary"')
        ->and($frontendTest)->toContain('renders the voucher shell as a sleek operational header')
        ->and($compass)->toContain('Voucher Detail Primary Workflow Compression — Slice 1')
        ->and($settlementCompass)->toContain('Voucher Detail Primary Workflow Compression — Slice 1');
});

it('documents voucher detail primary workflow compression slice 2', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/629-voucher-detail-primary-workflow-compression-slice-2.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Voucher Detail Primary Workflow Compression — Slice 2')
        ->toContain('compact readiness strip')
        ->toContain('Presentation-only primary-workflow compression')
        ->and($page)->toContain('data-testid="cockpit-voucher-detail-primary-readiness-strip"')
        ->and($page)->toContain('data-testid="cockpit-voucher-detail-primary-readiness-item"')
        ->and($page)->toContain('data-testid="cockpit-voucher-detail-lifecycle-guidance"')
        ->and($frontendTest)->toContain("expect(guidance.element.tagName.toLowerCase()).toBe('details')")
        ->and($compass)->toContain('Voucher Detail Primary Workflow Compression — Slice 2')
        ->and($settlementCompass)->toContain('Voucher Detail Primary Workflow Compression — Slice 2');
});
