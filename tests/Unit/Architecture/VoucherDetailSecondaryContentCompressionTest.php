<?php

declare(strict_types=1);

it('documents voucher detail secondary content compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/631-voucher-detail-secondary-content-compression-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Voucher Detail Secondary Content Compression — Slice 1')
        ->toContain('closed-by-default URL-details disclosure')
        ->toContain('Presentation-only claim-link compression')
        ->and($page)->toContain('data-testid="cockpit-voucher-detail-distribution-links-panel"')
        ->and($page)->toContain('URL details')
        ->and($frontendTest)->toContain("expect(panel.element.tagName.toLowerCase()).toBe('details')")
        ->and($frontendTest)->toContain("expect(panel.attributes('open')).toBeUndefined()")
        ->and($compass)->toContain('Voucher Detail Secondary Content Compression — Slice 1')
        ->and($settlementCompass)->toContain('Voucher Detail Secondary Content Compression — Slice 1');
});

it('documents voucher detail secondary content compression slice 2', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/632-voucher-detail-secondary-content-compression-slice-2.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Voucher Detail Secondary Content Compression — Slice 2')
        ->toContain('closed three-service disclosure')
        ->toContain('Presentation-only integration-summary compression')
        ->and($page)->toContain('data-testid="cockpit-voucher-integration-summary-panel"')
        ->and($page)->toContain('3 service summaries')
        ->and($frontendTest)->toContain("expect(panel.attributes('open')).toBeUndefined()")
        ->and($frontendTest)->toContain("expect(cards[0].classes()).toContain('p-3')")
        ->and($compass)->toContain('Voucher Detail Secondary Content Compression — Slice 2')
        ->and($settlementCompass)->toContain('Voucher Detail Secondary Content Compression — Slice 2');
});
