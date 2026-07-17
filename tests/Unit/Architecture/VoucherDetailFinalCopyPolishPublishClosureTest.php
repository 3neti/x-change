<?php

declare(strict_types=1);

it('documents voucher detail final copy polish host publish closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/462-voucher-detail-final-copy-polish-slice-2-publish-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Voucher Detail Final Copy Polish — Slice 2 — Host Publish / Closure')
        ->and($report)->toContain('checked 60, ok 60, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Pay Code Detail')
        ->and($report)->toContain('Pay Code inspection')
        ->and($report)->toContain('npm run build')
        ->and($report)->toContain('No read-model behavior')
        ->and($cockpitCompass)->toContain('Voucher Detail Final Copy Polish — Slice 2 — Host Publish / Closure')
        ->and($cockpitCompass)->toContain('reports/462-voucher-detail-final-copy-polish-slice-2-publish-closure.md')
        ->and($settlementCompass)->toContain('Voucher Detail Final Copy Polish — Slice 2 — Host Publish / Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/462-voucher-detail-final-copy-polish-slice-2-publish-closure.md');
});
