<?php

declare(strict_types=1);

it('documents seeded diagnostic fixture human visual confirmation', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/094-seeded-diagnostic-fixture-human-visual-confirmation.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4R — Seeded Diagnostic Fixture Human Visual Confirmation')
        ->and($report)->toContain('Status: Pass — accepted by human')
        ->and($report)->toContain('Pay Code PC-LOCAL-DIAGNOSTIC issued')
        ->and($report)->toContain('journal: recorded')
        ->and($report)->toContain('action: not_wired')
        ->and($report)->toContain('feedback: not_wired')
        ->and($report)->toContain('journal-entry-local-fixture')
        ->and($report)->toContain('Source: local_fixture')
        ->and($report)->toContain('ERN-LOCAL-COCKPIT-0001')
        ->and($report)->toContain('cockpit.operator_issuance_activity.fixture')
        ->and($report)->toContain('Diagnostic: Journal recorded')
        ->and($report)->toContain('Action: none')
        ->and($report)->toContain('Read-only: yes')
        ->and($report)->toContain('corr-local-cockpit-diagnostic')
        ->and($report)->toContain('actor_id: 5')
        ->and($report)->toContain('Viewing the dashboard does not write a journal entry.')
        ->and($report)->toContain('Cockpit Mutation Wave 5A — Real Operator Activity Rollout Readiness Decision')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4R — Seeded Diagnostic Fixture Human Visual Confirmation')
        ->and($cockpitCompass)->toContain('reports/094-seeded-diagnostic-fixture-human-visual-confirmation.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4R — Seeded Diagnostic Fixture Human Visual Confirmation')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/094-seeded-diagnostic-fixture-human-visual-confirmation.md');
});
