<?php

declare(strict_types=1);

it('documents cockpit wave 62b manual distribution next capability decision matrix', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/365-wave-62b-manual-distribution-next-capability-decision-matrix.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 62B — Manual Distribution Next Capability Decision Matrix')
        ->and($report)->toContain('Manual copy operational hardening')
        ->and($report)->toContain('Recommended')
        ->and($report)->toContain('Copy event telemetry')
        ->and($report)->toContain('x-feedback delivery from Cockpit')
        ->and($report)->toContain('Campaign dispatch from Cockpit')
        ->and($report)->toContain('Short-link generation')
        ->and($report)->toContain('QR asset generation')
        ->and($report)->toContain('Print artifact generation')
        ->and($report)->toContain('Proceed next with:')
        ->and($report)->toContain('Verify the accepted guidance remains visible after asset publishing.')
        ->and($report)->toContain('Keep copy controls browser-local.')
        ->and($report)->toContain('Keep copy attempts non-persistent.')
        ->and($report)->toContain('Keep delivery disabled.')
        ->and($report)->toContain('Add regression guards that prevent accidental backend endpoint calls from copy UI.')
        ->and($report)->toContain('The decision does not approve:')
        ->and($report)->toContain('Cockpit Wave 62C — Manual Distribution Operational Readiness Closure')
        ->and($cockpitCompass)->toContain('Cockpit Wave 62B — Manual Distribution Next Capability Decision Matrix')
        ->and($cockpitCompass)->toContain('reports/365-wave-62b-manual-distribution-next-capability-decision-matrix.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 62B — Manual Distribution Next Capability Decision Matrix')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/365-wave-62b-manual-distribution-next-capability-decision-matrix.md');
});
