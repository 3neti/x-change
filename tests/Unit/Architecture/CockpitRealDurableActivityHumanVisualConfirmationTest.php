<?php

declare(strict_types=1);

it('documents real durable activity human visual confirmation', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/097-real-durable-activity-human-visual-confirmation.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5C — Real Durable Activity Human Visual Confirmation')
        ->and($report)->toContain('Status: Pass — accepted by human')
        ->and($report)->toContain('Pay Code MCPC issued')
        ->and($report)->toContain('PHP 25 issued through Quick Generate')
        ->and($report)->toContain('journal: not_wired')
        ->and($report)->toContain('action: not_wired')
        ->and($report)->toContain('feedback: not_wired')
        ->and($report)->toContain('Writes journal: no')
        ->and($report)->toContain('durable-operator-issuance-activity-read-model')
        ->and($report)->toContain('Diagnostic: Journal handoff not wired')
        ->and($report)->toContain('Action: configure_when_ready')
        ->and($report)->toContain('Read-only: yes')
        ->and($report)->toContain('corr-cockpit-real-activity-5b')
        ->and($report)->toContain('Pay Code PC-LOCAL-DIAGNOSTIC issued')
        ->and($report)->toContain('journal-entry-local-fixture')
        ->and($report)->toContain('Pass — accepted by human')
        ->and($report)->toContain('Cockpit Mutation Wave 5D — Durable Activity Local Opt-In Closure / Cleanup Decision')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5C — Real Durable Activity Human Visual Confirmation')
        ->and($cockpitCompass)->toContain('reports/097-real-durable-activity-human-visual-confirmation.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 5C — Real Durable Activity Human Visual Confirmation')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/097-real-durable-activity-human-visual-confirmation.md');
});
