<?php

declare(strict_types=1);

it('documents cockpit wave 64 manual distribution operator runbook workflow handoff closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/372-wave-64-manual-distribution-operator-runbook-workflow-handoff-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 64 — Manual Distribution Operator Runbook / Workflow Handoff Closure')
        ->and($report)->toContain('Complete / Operator runbook and workflow handoff boundary recorded.')
        ->and($report)->toContain('Cockpit remains responsible for showing and copying the beneficiary URL.')
        ->and($report)->toContain('The approved external workflow remains responsible for actual communication and delivery evidence.')
        ->and($report)->toContain('Cockpit Wave 64A — Manual Distribution Operator Runbook.')
        ->and($report)->toContain('Cockpit Wave 64B — Manual Distribution Workflow Handoff Boundary.')
        ->and($report)->toContain('Share it only through an approved external workflow.')
        ->and($report)->toContain('Verify the recipient before sending through that workflow.')
        ->and($report)->toContain('Cockpit does not own actual delivery.')
        ->and($report)->toContain('Message sending.')
        ->and($report)->toContain('Delivery evidence.')
        ->and($report)->toContain('SMS, email, webhook, in-app, or campaign delivery from Cockpit.')
        ->and($report)->toContain('Copy telemetry persistence.')
        ->and($report)->toContain('checked 59, ok 59, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Cockpit Wave 65 — Manual Distribution External Evidence Intake Decision')
        ->and($cockpitCompass)->toContain('Cockpit Wave 64 — Manual Distribution Operator Runbook / Workflow Handoff Closure')
        ->and($cockpitCompass)->toContain('reports/372-wave-64-manual-distribution-operator-runbook-workflow-handoff-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 64 — Manual Distribution Operator Runbook / Workflow Handoff Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/372-wave-64-manual-distribution-operator-runbook-workflow-handoff-closure.md');
});
