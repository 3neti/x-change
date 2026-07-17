<?php

declare(strict_types=1);

it('documents voucher detail final copy polish inspection copy slice', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/461-voucher-detail-final-copy-polish-slice-1-inspection-copy.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Voucher Detail Final Copy Polish — Slice 1 — Inspection Copy')
        ->and($report)->toContain('Pay Code Detail')
        ->and($report)->toContain('Pay Code inspection')
        ->and($report)->toContain('Pay Code facts')
        ->and($report)->toContain('Lifecycle timeline')
        ->and($report)->toContain('Evidence status')
        ->and($report)->toContain('Delivery status')
        ->and($report)->toContain('Audit and follow-up status')
        ->and($report)->toContain('No read-model behavior')
        ->and($cockpitCompass)->toContain('Voucher Detail Final Copy Polish — Slice 1 — Inspection Copy')
        ->and($cockpitCompass)->toContain('reports/461-voucher-detail-final-copy-polish-slice-1-inspection-copy.md')
        ->and($settlementCompass)->toContain('Voucher Detail Final Copy Polish — Slice 1 — Inspection Copy')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/461-voucher-detail-final-copy-polish-slice-1-inspection-copy.md');
});
