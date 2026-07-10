<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff operator diagnostics manual browser verification handoff', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/088-durable-activity-journal-handoff-operator-diagnostics-manual-browser-verification.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4L — Durable Activity Journal Handoff Operator Diagnostics Manual Browser Verification')
        ->and($report)->toContain('Status: Handoff recorded')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('6 routes registered')
        ->and($report)->toContain('checked 55')
        ->and($report)->toContain('stale 0')
        ->and($report)->toContain('No fresh Cockpit render exception was observed.')
        ->and($report)->toContain('npm run build')
        ->and($report)->toContain('74 passed')
        ->and($report)->toContain('476 tests')
        ->and($report)->toContain('Manual Browser Verification Needed')
        ->and($report)->toContain('no retry button is visible')
        ->and($report)->toContain('Cockpit Mutation Wave 4M — Durable Activity Journal Handoff Operator Diagnostics Human Visual Confirmation Record')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4L — Durable Activity Journal Handoff Operator Diagnostics Manual Browser Verification')
        ->and($cockpitCompass)->toContain('reports/088-durable-activity-journal-handoff-operator-diagnostics-manual-browser-verification.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4L — Durable Activity Journal Handoff Operator Diagnostics Manual Browser Verification')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/088-durable-activity-journal-handoff-operator-diagnostics-manual-browser-verification.md');
});
