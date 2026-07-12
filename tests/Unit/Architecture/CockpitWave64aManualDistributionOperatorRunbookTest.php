<?php

declare(strict_types=1);

it('documents cockpit wave 64a manual distribution operator runbook', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/370-wave-64a-manual-distribution-operator-runbook.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 64A — Manual Distribution Operator Runbook')
        ->and($report)->toContain('The Pay Code is the intended Pay Code.')
        ->and($report)->toContain('The beneficiary URL is visible on Voucher Detail or Distribution Workspace.')
        ->and($report)->toContain('The recipient has been verified through an approved external workflow.')
        ->and($report)->toContain('Click `Copy beneficiary URL`.')
        ->and($report)->toContain('Paste the URL only into the approved external workflow.')
        ->and($report)->toContain('Treat beneficiary URLs as sensitive settlement access material.')
        ->and($report)->toContain('Do not treat copy as delivery confirmation.')
        ->and($report)->toContain('Do not assume copy creates a journal record.')
        ->and($report)->toContain('Browser-local.')
        ->and($report)->toContain('Non-persistent.')
        ->and($report)->toContain('Non-delivery.')
        ->and($report)->toContain('Non-telemetry.')
        ->and($report)->toContain('Stop and escalate before sharing if:')
        ->and($report)->toContain('Cockpit Wave 64B — Manual Distribution Workflow Handoff Boundary')
        ->and($cockpitCompass)->toContain('Cockpit Wave 64A — Manual Distribution Operator Runbook')
        ->and($cockpitCompass)->toContain('reports/370-wave-64a-manual-distribution-operator-runbook.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 64A — Manual Distribution Operator Runbook')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/370-wave-64a-manual-distribution-operator-runbook.md');
});
