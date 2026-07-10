<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff operator diagnostics boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/086-durable-activity-journal-handoff-operator-diagnostics.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $diagnostics = file_get_contents($packageRoot.'/src/Services/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffDiagnostics.php');
    $presenter = file_get_contents($packageRoot.'/src/Services/Cockpit/DefaultCockpitOperatorIssuanceActivityPresenter.php');
    $panel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitOperatorIssuanceActivityPanel.vue');
    $types = file_get_contents($packageRoot.'/resources/js/cockpit/types.ts');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDashboardHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4J — Durable Activity Journal Handoff Operator Diagnostics')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('metadata.journal_handoff.diagnostic')
        ->and($report)->toContain('No buttons, retry controls, mutation controls, or workflow actions were added.')
        ->and($report)->toContain('Cockpit Mutation Wave 4K — Durable Activity Journal Handoff Operator Diagnostics Host Publish / Verification')
        ->and($diagnostics)->toContain('class CockpitOperatorIssuanceActivityJournalHandoffDiagnostics')
        ->and($diagnostics)->toContain('retry_enabled')
        ->and($diagnostics)->toContain('mutation_enabled')
        ->and($diagnostics)->toContain('raw_payloads_exposed')
        ->and($presenter)->toContain('diagnostic')
        ->and($panel)->toContain('cockpit-operator-issuance-activity-journal-diagnostic')
        ->and($panel)->toContain('Diagnostic:')
        ->and($panel)->toContain('Read-only:')
        ->and($types)->toContain('diagnostic?:')
        ->and($frontendTest)->toContain('Diagnostic: Journal recorded')
        ->and($frontendTest)->toContain('cockpit-operator-issuance-activity-journal-retry')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4J — Durable Activity Journal Handoff Operator Diagnostics')
        ->and($cockpitCompass)->toContain('reports/086-durable-activity-journal-handoff-operator-diagnostics.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4J — Durable Activity Journal Handoff Operator Diagnostics')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/086-durable-activity-journal-handoff-operator-diagnostics.md');
});
