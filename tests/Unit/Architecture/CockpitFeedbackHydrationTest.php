<?php

declare(strict_types=1);

it('documents the feedback cockpit hydration slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/026-feedback-cockpit-hydration.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Integration Slice 2C — Feedback Cockpit Hydration')
        ->and($report)->toContain('Hydrate x-feedback delivery summaries')
        ->and($report)->toContain('does not:')
        ->and($report)->toContain('send feedback')
        ->and($report)->toContain('expose recipient addresses')
        ->and($report)->toContain('move money')
        ->and($cockpitCompass)->toContain('Completed Host Integration Slice 2C — Feedback Cockpit Hydration')
        ->and($cockpitCompass)->toContain('reports/026-feedback-cockpit-hydration.md')
        ->and($settlementCompass)->toContain('x-change Host Integration Slice 2C — Feedback Cockpit Hydration')
        ->and($settlementCompass)->toContain('No feedback delivery, retry execution, recipient address exposure, provider payload exposure, raw payload exposure, journal writes, action execution, provider calls, voucher mutation, wallet access, or money movement were added');
});
