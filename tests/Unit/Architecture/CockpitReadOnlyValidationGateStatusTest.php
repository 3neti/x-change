<?php

declare(strict_types=1);

it('documents the read-only cockpit validation gate status', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/038-read-only-validation-gate-status.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Validation Checkpoint 6 — Read-Only Validation Gate Status')
        ->and($report)->toContain('Status: Gate passed based on human visual browser confirmation')
        ->and($report)->toContain('Read-only Cockpit validation gate: PASS')
        ->and($report)->toContain('Human reviewer manually opened and tested the required Cockpit routes with no issues reported')
        ->and($report)->toContain('Still Not Authorized Without Separate Approval')
        ->and($report)->toContain('Cockpit mutation routes')
        ->and($report)->toContain('money movement')
        ->and($report)->toContain('Work Now Allowed')
        ->and($report)->toContain('mutation-capable Cockpit planning, if explicitly requested')
        ->and($cockpitCompass)->toContain('Host Validation Checkpoint 6 — Read-Only Validation Gate Status')
        ->and($cockpitCompass)->toContain('Read-only Cockpit validation gate is recorded as `Pass`')
        ->and($cockpitCompass)->toContain('reports/038-read-only-validation-gate-status.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 6 — Read-Only Validation Gate Status')
        ->and($settlementCompass)->toContain('Read-only Cockpit validation gate is recorded as `Pass`');
});
