<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff read model exposure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/085-durable-activity-journal-handoff-read-model-exposure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $readModelProvider = file_get_contents($packageRoot.'/src/Services/Cockpit/DurableCockpitOperatorIssuanceActivityReadModelProvider.php');
    $presenter = file_get_contents($packageRoot.'/src/Services/Cockpit/DefaultCockpitOperatorIssuanceActivityPresenter.php');
    $panel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitOperatorIssuanceActivityPanel.vue');
    $featureTest = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitOperatorIssuanceActivityDurableReadModelAdapterTest.php');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDashboardHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4I — Durable Activity Journal Handoff Read Model Exposure')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('metadata.journal_handoff')
        ->and($report)->toContain('No buttons, retry controls, mutation controls, or workflow actions were added.')
        ->and($report)->toContain('Cockpit Mutation Wave 4J — Durable Activity Journal Handoff Operator Diagnostics')
        ->and($readModelProvider)->toContain('journalHandoffResult')
        ->and($readModelProvider)->toContain('safeJournalHandoffMetadata')
        ->and($readModelProvider)->toContain('reference_number')
        ->and($readModelProvider)->toContain('event_type')
        ->and($presenter)->toContain('journal_handoff')
        ->and($panel)->toContain('cockpit-operator-issuance-activity-journal-summary')
        ->and($panel)->toContain('Journal entry:')
        ->and($panel)->toContain('Writes journal:')
        ->and($featureTest)->toContain('exposes persisted journal handoff summary as safe read-only presentation metadata')
        ->and($frontendTest)->toContain('Journal entry: journal-entry-1')
        ->and($frontendTest)->toContain('must-not-render')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4I — Durable Activity Journal Handoff Read Model Exposure')
        ->and($cockpitCompass)->toContain('reports/085-durable-activity-journal-handoff-read-model-exposure.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4I — Durable Activity Journal Handoff Read Model Exposure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/085-durable-activity-journal-handoff-read-model-exposure.md');
});
