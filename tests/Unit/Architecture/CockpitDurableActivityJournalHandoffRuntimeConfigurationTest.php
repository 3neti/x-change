<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff runtime configuration seam', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/078-durable-activity-journal-handoff-contract-null-runtime-configuration.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $config = file_get_contents($packageRoot.'/config/x-change.php');
    $provider = file_get_contents($packageRoot.'/src/Providers/XChangeServiceProvider.php');
    $runtimeTest = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffRuntimeConfigurationTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4B — Durable Activity Journal Handoff Contract / Null Runtime Configuration')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('x-change.cockpit.operator_issuance_activity.journal_handoff')
        ->and($report)->toContain('NullCockpitOperatorIssuanceActivityJournalHandoff')
        ->and($report)->toContain('No x-journal runtime calls')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('Cockpit Mutation Wave 4C — Durable Activity Journal Payload Mapping Baseline')
        ->and($config)->toContain("'journal_handoff' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_JOURNAL_HANDOFF')")
        ->and($config)->toContain("'available_journal_handoffs' => [")
        ->and($provider)->toContain("config(\n                'x-change.cockpit.operator_issuance_activity.journal_handoff'")
        ->and($runtimeTest)->toContain('FakeConfiguredCockpitActivityJournalHandoff')
        ->and($runtimeTest)->toContain('writes_journal')->toContain('toBeFalse')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4B — Durable Activity Journal Handoff Contract / Null Runtime Configuration')
        ->and($cockpitCompass)->toContain('reports/078-durable-activity-journal-handoff-contract-null-runtime-configuration.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4B — Durable Activity Journal Handoff Contract / Null Runtime Configuration')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/078-durable-activity-journal-handoff-contract-null-runtime-configuration.md');
});
