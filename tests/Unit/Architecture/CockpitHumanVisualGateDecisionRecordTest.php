<?php

declare(strict_types=1);

it('documents the human visual gate decision record template', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/041-human-visual-gate-decision-record-template.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Validation Checkpoint 9 — Human Visual Gate Decision Record Template')
        ->and($report)->toContain('Decision record scaffolded; gate decision still pending')
        ->and($report)->toContain('Inputs Required Before Decision')
        ->and($report)->toContain('reports/040-human-visual-evidence-intake-template.md')
        ->and($report)->toContain('Allowed final gate decisions')
        ->and($report)->toContain('Blocked — accepted by human')
        ->and($report)->toContain('Required Propagation After Decision')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Host Validation Checkpoint 9 — Human Visual Gate Decision Record Template')
        ->and($cockpitCompass)->toContain('Decision record scaffolded; gate decision still pending')
        ->and($cockpitCompass)->toContain('reports/041-human-visual-gate-decision-record-template.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 9 — Human Visual Gate Decision Record Template')
        ->and($settlementCompass)->toContain('Added the final decision record template for closing the currently blocked read-only Cockpit validation gate');
});
