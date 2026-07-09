<?php

declare(strict_types=1);

it('documents the blocked read-only cockpit validation gate audit', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/039-blocked-gate-audit-allowed-work-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Validation Checkpoint 7 — Blocked Gate Audit / Allowed Work Boundary')
        ->and($report)->toContain('Gate re-audited; still blocked pending human visual browser confirmation')
        ->and($report)->toContain('Allowed Work While Blocked')
        ->and($report)->toContain('test-only guard hardening')
        ->and($report)->toContain('Prohibited Work While Blocked')
        ->and($report)->toContain('Quick Generate issuance mutation')
        ->and($report)->toContain('money movement')
        ->and($report)->toContain('No human pass/fail/blocked result has been supplied')
        ->and($cockpitCompass)->toContain('Host Validation Checkpoint 7 — Blocked Gate Audit / Allowed Work Boundary')
        ->and($cockpitCompass)->toContain('Gate re-audited; still blocked pending human visual browser confirmation')
        ->and($cockpitCompass)->toContain('reports/039-blocked-gate-audit-allowed-work-boundary.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 7 — Blocked Gate Audit / Allowed Work Boundary')
        ->and($settlementCompass)->toContain('The read-only validation gate was re-audited and remains blocked');
});
