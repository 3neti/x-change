<?php

declare(strict_types=1);

it('documents cockpit wave 63 manual copy operational hardening closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/369-wave-63-manual-copy-operational-hardening-closure.md';
    $manualCopyTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitManualCopyButton.test.ts';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $manualCopyTest = file_get_contents($manualCopyTestPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 63 — Manual Copy Operational Hardening Closure')
        ->and($report)->toContain('Complete / Manual copy backend-transport guard strengthened.')
        ->and($report)->toContain('Cockpit Wave 63A — Manual Copy Operational Hardening Contract.')
        ->and($report)->toContain('Cockpit Wave 63B — Manual Copy No-Backend-Interaction Regression Guard.')
        ->and($report)->toContain('Browser-local.')
        ->and($report)->toContain('Non-persistent.')
        ->and($report)->toContain('Non-delivery.')
        ->and($report)->toContain('Non-telemetry.')
        ->and($report)->toContain('fetch')
        ->and($report)->toContain('navigator.sendBeacon')
        ->and($report)->toContain('XMLHttpRequest')
        ->and($report)->toContain('CockpitManualCopyButton frontend tests: 5 passed')
        ->and($report)->toContain('Published assets: checked 59, ok 59, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Cockpit Wave 64 — Manual Distribution Operator Runbook / Workflow Handoff')
        ->and($manualCopyTest)->toContain('does not use backend transport APIs while copying manually')
        ->and($cockpitCompass)->toContain('Cockpit Wave 63 — Manual Copy Operational Hardening Closure')
        ->and($cockpitCompass)->toContain('reports/369-wave-63-manual-copy-operational-hardening-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 63 — Manual Copy Operational Hardening Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/369-wave-63-manual-copy-operational-hardening-closure.md');
});
