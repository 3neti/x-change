<?php

declare(strict_types=1);

it('documents voucher detail browser feedback refinement slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/637-voucher-detail-browser-feedback-refinement-slice-1.md');
    $audit = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitVoucherAuditPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Voucher Detail Browser Feedback Refinement — Slice 1')
        ->toContain('one scan row')
        ->toContain('Presentation-only audit-summary compression')
        ->and($audit)->toContain('data-testid="cockpit-voucher-audit-summary"')
        ->and($audit)->toContain('disabled follow-ups')
        ->and($audit)->not->toContain('View details')
        ->and($frontendTest)->toContain("expect(wrapper.find('[data-testid=\"cockpit-voucher-audit-panel\"]').classes()).toContain('py-2.5')")
        ->and($compass)->toContain('Voucher Detail Browser Feedback Refinement — Slice 1')
        ->and($settlementCompass)->toContain('Voucher Detail Browser Feedback Refinement — Slice 1');
});
