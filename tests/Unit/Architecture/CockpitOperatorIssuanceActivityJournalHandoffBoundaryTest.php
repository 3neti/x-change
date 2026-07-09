<?php

declare(strict_types=1);

it('documents the cockpit operator issuance activity journal handoff boundary without journal writes', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/053-operator-issuance-activity-journal-handoff-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2C — Journal Handoff Boundary')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('NullCockpitOperatorIssuanceActivityJournalHandoff')
        ->and($report)->toContain('writes_journal: false')
        ->and($report)->toContain('does not write to it in this slice')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('x-journal runtime calls')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2C — Journal Handoff Boundary')
        ->and($cockpitCompass)->toContain('reports/053-operator-issuance-activity-journal-handoff-boundary.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2C — Journal Handoff Boundary')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/053-operator-issuance-activity-journal-handoff-boundary.md');
});
