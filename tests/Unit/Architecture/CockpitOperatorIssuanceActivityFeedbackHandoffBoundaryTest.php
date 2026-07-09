<?php

declare(strict_types=1);

it('documents the cockpit operator issuance activity feedback handoff boundary without notification delivery', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/055-operator-issuance-activity-feedback-handoff-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2E — Feedback Handoff Boundary')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('NullCockpitOperatorIssuanceActivityFeedbackHandoff')
        ->and($report)->toContain('sends_feedback: false')
        ->and($report)->toContain('does not call x-feedback in this slice')
        ->and($report)->toContain('x-feedback runtime calls')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2E — Feedback Handoff Boundary')
        ->and($cockpitCompass)->toContain('reports/055-operator-issuance-activity-feedback-handoff-boundary.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2E — Feedback Handoff Boundary')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/055-operator-issuance-activity-feedback-handoff-boundary.md');
});
