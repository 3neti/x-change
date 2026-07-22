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

it('documents voucher detail secondary content compression slice 3 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/633-voucher-detail-secondary-content-compression-slice-3-closure.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $overview = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitVoucherOverviewPanel.vue');
    $timeline = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitVoucherTimelinePanel.vue');
    $evidence = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitVoucherEvidencePanel.vue');
    $distribution = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitVoucherDistributionPanel.vue');
    $audit = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitVoucherAuditPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts');
    $hostPage = file_get_contents(dirname($packageRoot, 2).'/resources/js/cockpit/pages/VoucherDetail.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Voucher Detail Secondary Content Compression — Slice 3 / Closure')
        ->toContain('closed count-bearing disclosures')
        ->toContain('Presentation-only secondary-content compression closure')
        ->and($page)->toContain('data-testid="cockpit-voucher-secondary-content"')
        ->and($page)->toContain('data-testid="cockpit-voucher-supporting-evidence-grid"')
        ->and($overview)->toContain('{{ items.length }} facts')
        ->and($timeline)->toContain('{{ items.length }} events')
        ->and($evidence)->toContain('{{ items.length }} facts')
        ->and($distribution)->toContain('{{ items.length }} channels')
        ->and($audit)->toContain('class="rounded-xl border border-slate-200 bg-white px-4 py-2.5')
        ->and($frontendTest)->toContain('[data-testid="cockpit-voucher-overview-panel"]')
        ->and($frontendTest)->toContain(".attributes('open')")
        ->and($hostPage)->toContain('AUTO-GENERATED BY x-change:install')
        ->toContain('data-testid="cockpit-voucher-secondary-content"')
        ->and($compass)->toContain('Voucher Detail Secondary Content Compression — Slice 3 Closure')
        ->and($settlementCompass)->toContain('Voucher Detail Secondary Content Compression — Slice 3');
});
