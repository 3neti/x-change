<?php

declare(strict_types=1);

it('documents cockpit wave 64b manual distribution workflow handoff boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/371-wave-64b-manual-distribution-workflow-handoff-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 64B — Manual Distribution Workflow Handoff Boundary')
        ->and($report)->toContain('Cockpit presents and copies the beneficiary URL.')
        ->and($report)->toContain('The external workflow is responsible for actual delivery')
        ->and($report)->toContain('Display the canonical beneficiary Pay Code URL.')
        ->and($report)->toContain('Provide browser-local copy.')
        ->and($report)->toContain('Recipient verification.')
        ->and($report)->toContain('Message sending.')
        ->and($report)->toContain('Delivery evidence.')
        ->and($report)->toContain('Copying the URL is not delivery.')
        ->and($report)->toContain('Copying the URL is not feedback state.')
        ->and($report)->toContain('Copying the URL is not lifecycle truth.')
        ->and($report)->toContain('x-feedback for communication delivery and delivery records.')
        ->and($report)->toContain('x-campaign for campaign/program dispatch.')
        ->and($report)->toContain('x-journal for durable audit facts.')
        ->and($report)->toContain('x-action for operator workflow continuation.')
        ->and($report)->toContain('Cockpit Wave 64C — Manual Distribution Operator Runbook / Workflow Handoff Closure')
        ->and($cockpitCompass)->toContain('Cockpit Wave 64B — Manual Distribution Workflow Handoff Boundary')
        ->and($cockpitCompass)->toContain('reports/371-wave-64b-manual-distribution-workflow-handoff-boundary.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 64B — Manual Distribution Workflow Handoff Boundary')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/371-wave-64b-manual-distribution-workflow-handoff-boundary.md');
});
