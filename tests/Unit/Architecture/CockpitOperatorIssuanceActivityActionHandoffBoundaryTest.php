<?php

declare(strict_types=1);

it('documents the cockpit operator issuance activity action handoff boundary without action execution', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/054-operator-issuance-activity-action-handoff-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2D — Action Handoff Boundary')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('NullCockpitOperatorIssuanceActivityActionHandoff')
        ->and($report)->toContain('executes_action: false')
        ->and($report)->toContain('does not execute x-action in this slice')
        ->and($report)->toContain('x-action runtime calls')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2D — Action Handoff Boundary')
        ->and($cockpitCompass)->toContain('reports/054-operator-issuance-activity-action-handoff-boundary.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2D — Action Handoff Boundary')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/054-operator-issuance-activity-action-handoff-boundary.md');
});
