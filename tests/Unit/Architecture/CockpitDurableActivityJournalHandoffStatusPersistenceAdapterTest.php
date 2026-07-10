<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff status persistence adapter', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/083-durable-activity-journal-handoff-status-persistence-adapter.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $config = file_get_contents($packageRoot.'/config/x-change.php');
    $provider = file_get_contents($packageRoot.'/src/Providers/XChangeServiceProvider.php');
    $adapter = file_get_contents($packageRoot.'/src/Services/Cockpit/DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector.php');
    $featureTest = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffStatusPersistenceAdapterTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4G — Durable Activity Journal Handoff Status Persistence Adapter')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector')
        ->and($report)->toContain('journal_handoff_status_projector')
        ->and($report)->toContain('preserve action and feedback handoff statuses')
        ->and($report)->toContain('No UI changes')
        ->and($report)->toContain('Cockpit Mutation Wave 4H — Durable Activity Journal Handoff Invocation Pipeline')
        ->and($config)->toContain("'journal_handoff_status_projector' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_JOURNAL_HANDOFF_STATUS_PROJECTOR')")
        ->and($config)->toContain("'available_journal_handoff_status_projectors' => [")
        ->and($provider)->toContain('CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class')
        ->and($provider)->toContain('journal_handoff_status_projector')
        ->and($adapter)->toContain('class DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector')
        ->and($adapter)->toContain('CockpitOperatorIssuanceActivity::query()')
        ->and($adapter)->toContain('journal_handoff_status')
        ->and($adapter)->not->toContain('raw_payload')
        ->and($adapter)->not->toContain('provider_payload')
        ->and($featureTest)->toContain('persists journal handoff status and safe metadata')
        ->and($featureTest)->toContain('no ops when the durable activity row cannot be found')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4G — Durable Activity Journal Handoff Status Persistence Adapter')
        ->and($cockpitCompass)->toContain('reports/083-durable-activity-journal-handoff-status-persistence-adapter.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4G — Durable Activity Journal Handoff Status Persistence Adapter')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/083-durable-activity-journal-handoff-status-persistence-adapter.md');
});
