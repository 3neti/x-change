<?php

declare(strict_types=1);

it('documents the cockpit operator issuance activity ui rendering boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/058-operator-issuance-activity-ui-rendering-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2H — Activity UI Rendering Boundary')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityPanel')
        ->and($report)->toContain('operator_issuance_activity_read_model.presentations')
        ->and($report)->toContain('read-only dashboard rendering')
        ->and($report)->toContain('no mutation controls')
        ->and($report)->toContain('no journal writes')
        ->and($report)->toContain('no action execution')
        ->and($report)->toContain('no feedback delivery')
        ->and($report)->toContain('no money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2H — Activity UI Rendering Boundary')
        ->and($cockpitCompass)->toContain('reports/058-operator-issuance-activity-ui-rendering-boundary.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2H — Activity UI Rendering Boundary')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/058-operator-issuance-activity-ui-rendering-boundary.md');
});
