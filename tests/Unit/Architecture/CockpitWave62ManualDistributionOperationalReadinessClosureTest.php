<?php

declare(strict_types=1);

it('documents cockpit wave 62 manual distribution operational readiness closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/366-wave-62-manual-distribution-operational-readiness-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 62 — Manual Distribution Operational Readiness Closure')
        ->and($report)->toContain('Complete / Manual copy operational hardening selected.')
        ->and($report)->toContain('Manual copy is operational for Voucher Detail and Distribution Workspace.')
        ->and($report)->toContain('Cockpit Wave 62A — Manual Distribution Operational Readiness Inventory.')
        ->and($report)->toContain('Cockpit Wave 62B — Manual Distribution Next Capability Decision Matrix.')
        ->and($report)->toContain('manual-copy-operational / automated-distribution-not-authorized')
        ->and($report)->toContain('Manual copy operational hardening')
        ->and($report)->toContain('Voucher Detail beneficiary URL presentation.')
        ->and($report)->toContain('Distribution Workspace beneficiary URL presentation.')
        ->and($report)->toContain('Browser-local copy controls.')
        ->and($report)->toContain('checked 59, ok 59, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Copy event telemetry.')
        ->and($report)->toContain('Cockpit-triggered x-feedback delivery.')
        ->and($report)->toContain('Campaign dispatch.')
        ->and($report)->toContain('Short-link generation.')
        ->and($report)->toContain('QR asset generation.')
        ->and($report)->toContain('Cockpit Wave 63 — Manual Copy Operational Hardening')
        ->and($cockpitCompass)->toContain('Cockpit Wave 62 — Manual Distribution Operational Readiness Closure')
        ->and($cockpitCompass)->toContain('reports/366-wave-62-manual-distribution-operational-readiness-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 62 — Manual Distribution Operational Readiness Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/366-wave-62-manual-distribution-operational-readiness-closure.md');
});
