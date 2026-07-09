<?php

declare(strict_types=1);

it('documents the voucher detail integration summary slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/028-voucher-detail-integration-summary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Integration Slice 2E — Voucher Detail Integration Summary')
        ->and($report)->toContain('voucher-level Journal / Action / Feedback integration summary')
        ->and($report)->toContain('status, count, and payload policy only')
        ->and($report)->toContain('does not:')
        ->and($report)->toContain('write journal entries')
        ->and($report)->toContain('move money')
        ->and($cockpitCompass)->toContain('Completed Host Integration Slice 2E — Voucher Detail Integration Summary')
        ->and($cockpitCompass)->toContain('reports/028-voucher-detail-integration-summary.md')
        ->and($settlementCompass)->toContain('x-change Host Integration Slice 2E — Voucher Detail Integration Summary')
        ->and($settlementCompass)->toContain('No new routes, journal writes, action execution, feedback delivery, retry execution, provider calls, raw payload exposure, voucher mutation, wallet access, or money movement were added');
});
