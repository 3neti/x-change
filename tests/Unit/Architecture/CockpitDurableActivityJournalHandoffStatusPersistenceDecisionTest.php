<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff status persistence decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/081-durable-activity-journal-handoff-status-persistence-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $adapter = file_get_contents($packageRoot.'/src/Services/Cockpit/XJournalCockpitOperatorIssuanceActivityJournalHandoff.php');
    $repository = file_get_contents($packageRoot.'/src/Contracts/CockpitOperatorIssuanceActivityRepositoryContract.php');
    $record = file_get_contents($packageRoot.'/src/Data/Cockpit/CockpitOperatorIssuanceActivityRecordData.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4E — Durable Activity Journal Handoff Status Persistence Decision')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('Decision: persist journal handoff status in a later explicit implementation slice')
        ->and($report)->toContain('Do not update durable activity rows inside `XJournalCockpitOperatorIssuanceActivityJournalHandoff`')
        ->and($report)->toContain('future status projector')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityJournalHandoffResultData')
        ->and($report)->toContain('journal_handoff_status')
        ->and($report)->toContain('No production code changes')
        ->and($report)->toContain('No UI changes')
        ->and($report)->toContain('Cockpit Mutation Wave 4F — Durable Activity Journal Handoff Status Projector Contract')
        ->and($adapter)->not->toContain('CockpitOperatorIssuanceActivityRepositoryContract')
        ->and($adapter)->not->toContain('journal_handoff_status')
        ->and($repository)->not->toContain('journal')
        ->and($record)->toContain('journal_handoff_status')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4E — Durable Activity Journal Handoff Status Persistence Decision')
        ->and($cockpitCompass)->toContain('reports/081-durable-activity-journal-handoff-status-persistence-decision.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4E — Durable Activity Journal Handoff Status Persistence Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/081-durable-activity-journal-handoff-status-persistence-decision.md');
});
