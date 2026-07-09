<?php

declare(strict_types=1);

it('documents the read-only cockpit validation gate status', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/038-read-only-validation-gate-status.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Validation Checkpoint 6 — Read-Only Validation Gate Status')
        ->and($report)->toContain('Status: Gate blocked pending human visual browser confirmation')
        ->and($report)->toContain('Read-only Cockpit validation gate: BLOCKED')
        ->and($report)->toContain('Human visual browser confirmation has not been recorded')
        ->and($report)->toContain('Explicitly Not Authorized')
        ->and($report)->toContain('Cockpit mutation routes')
        ->and($report)->toContain('money movement')
        ->and($report)->toContain('Work Still Allowed')
        ->and($report)->toContain('visual validation evidence capture')
        ->and($cockpitCompass)->toContain('Host Validation Checkpoint 6 — Read-Only Validation Gate Status')
        ->and($cockpitCompass)->toContain('Gate blocked pending human visual browser confirmation')
        ->and($cockpitCompass)->toContain('reports/038-read-only-validation-gate-status.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 6 — Read-Only Validation Gate Status')
        ->and($settlementCompass)->toContain('Read-only Cockpit validation remains blocked pending human visual browser confirmation');
});
