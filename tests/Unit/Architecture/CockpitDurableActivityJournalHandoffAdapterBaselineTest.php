<?php

declare(strict_types=1);

it('documents the cockpit durable activity x journal handoff adapter baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/080-durable-activity-journal-handoff-adapter-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $adapter = file_get_contents($packageRoot.'/src/Services/Cockpit/XJournalCockpitOperatorIssuanceActivityJournalHandoff.php');
    $test = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitOperatorIssuanceActivityXJournalHandoffAdapterTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4D — Durable Activity Journal Handoff Adapter Baseline')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('XJournalCockpitOperatorIssuanceActivityJournalHandoff')
        ->and($report)->toContain('ExecutionJournalRecorder')
        ->and($report)->toContain('idempotent replay')
        ->and($report)->toContain('failed_non_blocking')
        ->and($report)->toContain('No UI changes')
        ->and($adapter)->toContain('class XJournalCockpitOperatorIssuanceActivityJournalHandoff')
        ->and($adapter)->toContain('ExecutionJournalRecorder')
        ->and($adapter)->toContain('ExecutionJournalEntryData')
        ->and($adapter)->toContain('failed_non_blocking')
        ->and($adapter)->not->toContain('CockpitOperatorIssuanceActivityRepositoryContract')
        ->and($adapter)->not->toContain('CockpitOperatorIssuanceActivityRecorderContract')
        ->and($test)->toContain('records durable activity into x journal with idempotent replay semantics')
        ->and($test)->toContain('returns a non blocking failure result')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4D — Durable Activity Journal Handoff Adapter Baseline')
        ->and($cockpitCompass)->toContain('reports/080-durable-activity-journal-handoff-adapter-baseline.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4D — Durable Activity Journal Handoff Adapter Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/080-durable-activity-journal-handoff-adapter-baseline.md');
});
