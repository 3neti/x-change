<?php

it('documents voucher detail secondary panel cleanup slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/561-voucher-detail-secondary-panel-cleanup-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitVoucherAuditPanel.vue');
    $foundationTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts');
    $hydrationTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Voucher Detail Secondary Panel Cleanup — Slice 1')
        ->toContain('presentation-only')
        ->toContain('did not change routes, controllers, queries, read-model hydration')
        ->and($component)->toContain('Follow-up status')
        ->and($component)->toContain('Audit and follow-up details')
        ->and($component)->toContain('connectedAuditCount')
        ->and($component)->toContain('<details')
        ->and($foundationTest)->toContain('cockpit-voucher-audit-panel')
        ->and($foundationTest)->toContain('Follow-Ups')
        ->and($hydrationTest)->toContain('Follow-up actions are disabled from this page.')
        ->and($cockpitCompass)->toContain('Voucher Detail Secondary Panel Cleanup Slice 1')
        ->and($cockpitCompass)->toContain('reports/561-voucher-detail-secondary-panel-cleanup-slice-1.md')
        ->and($settlementCompass)->toContain('Voucher Detail Secondary Panel Cleanup Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/561-voucher-detail-secondary-panel-cleanup-slice-1.md');
});
