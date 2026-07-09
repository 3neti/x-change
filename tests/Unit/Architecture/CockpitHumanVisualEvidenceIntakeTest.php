<?php

declare(strict_types=1);

it('documents the human visual evidence intake template for the blocked cockpit gate', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/040-human-visual-evidence-intake-template.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Validation Checkpoint 8 — Human Visual Evidence Intake Template')
        ->and($report)->toContain('Evidence intake scaffolded; human visual browser confirmation still pending')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('Read-only Cockpit validation gate: BLOCKED')
        ->and($report)->toContain('Evidence Intake Form')
        ->and($report)->toContain('Human reviewer')
        ->and($report)->toContain('Overall result')
        ->and($report)->toContain('Surface Results')
        ->and($report)->toContain('Stop Conditions')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Host Validation Checkpoint 8 — Human Visual Evidence Intake Template')
        ->and($cockpitCompass)->toContain('Evidence intake scaffolded; human visual browser confirmation still pending')
        ->and($cockpitCompass)->toContain('reports/040-human-visual-evidence-intake-template.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 8 — Human Visual Evidence Intake Template')
        ->and($settlementCompass)->toContain('Added a single evidence intake record for resolving the read-only Cockpit validation gate');
});
