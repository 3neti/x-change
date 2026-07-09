<?php

declare(strict_types=1);

it('documents the read-only cockpit validation gate audit after pass', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/039-blocked-gate-audit-allowed-work-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Validation Checkpoint 7 — Blocked Gate Audit / Allowed Work Boundary')
        ->and($report)->toContain('Gate re-audited; human visual confirmation recorded as Pass')
        ->and($report)->toContain('Read-only Cockpit validation gate: PASS')
        ->and($report)->toContain('Allowed Work After Gate Pass')
        ->and($report)->toContain('mutation-capable Cockpit planning, if explicitly requested')
        ->and($report)->toContain('Still Prohibited Without Separate Approval')
        ->and($report)->toContain('Quick Generate issuance mutation')
        ->and($report)->toContain('money movement')
        ->and($report)->toContain('Human visual confirmation has been supplied and recorded as `Pass`')
        ->and($cockpitCompass)->toContain('Host Validation Checkpoint 7 — Blocked Gate Audit / Allowed Work Boundary')
        ->and($cockpitCompass)->toContain('human visual confirmation is recorded as `Pass`')
        ->and($cockpitCompass)->toContain('reports/039-blocked-gate-audit-allowed-work-boundary.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 7 — Blocked Gate Audit / Allowed Work Boundary')
        ->and($settlementCompass)->toContain('The read-only validation gate was re-audited and human visual confirmation is recorded as `Pass`');
});
