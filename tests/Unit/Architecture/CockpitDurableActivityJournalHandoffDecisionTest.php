<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff implementation decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/077-durable-activity-journal-handoff-implementation-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4A — Durable Activity Journal Handoff Implementation Decision')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('Proceed with a future opt-in journal handoff')
        ->and($report)->toContain('Do not write journal entries in this checkpoint')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityJournalHandoffContract')
        ->and($report)->toContain('NullCockpitOperatorIssuanceActivityJournalHandoff')
        ->and($report)->toContain('JournalEventRecorder')
        ->and($report)->toContain('OperatorActionJournalRecorder')
        ->and($report)->toContain('x-change.cockpit.operator_issuance_activity.journal_handoff')
        ->and($report)->toContain('No x-journal runtime calls')
        ->and($report)->toContain('No UI changes')
        ->and($report)->toContain('Cockpit Mutation Wave 4B — Durable Activity Journal Handoff Contract / Null Runtime Configuration')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4A — Durable Activity Journal Handoff Implementation Decision')
        ->and($cockpitCompass)->toContain('reports/077-durable-activity-journal-handoff-implementation-decision.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4A — Durable Activity Journal Handoff Implementation Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/077-durable-activity-journal-handoff-implementation-decision.md');
});
