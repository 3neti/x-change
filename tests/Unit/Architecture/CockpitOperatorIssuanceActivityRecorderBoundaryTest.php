<?php

declare(strict_types=1);

it('documents the cockpit operator issuance activity recorder boundary without durable side effects', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/052-operator-issuance-activity-recorder-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2B — Issuance Activity Recorder Boundary')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('NullCockpitOperatorIssuanceActivityRecorder')
        ->and($report)->toContain('Idempotency replays do not record duplicate operator activity')
        ->and($report)->toContain('Recorder failures are non-blocking')
        ->and($report)->toContain('persistence')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2B — Issuance Activity Recorder Boundary')
        ->and($cockpitCompass)->toContain('reports/052-operator-issuance-activity-recorder-boundary.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2B — Issuance Activity Recorder Boundary')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/052-operator-issuance-activity-recorder-boundary.md');
});
