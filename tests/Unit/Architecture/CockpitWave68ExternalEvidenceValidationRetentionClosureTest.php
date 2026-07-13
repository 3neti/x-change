<?php

declare(strict_types=1);

it('documents cockpit wave 68 external evidence validation retention closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/384-wave-68-external-evidence-validation-retention-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 68 — Manual Distribution External Evidence Validation / Retention Closure')
        ->and($report)->toContain('Complete / Validation and retention planning complete; runtime remains blocked.')
        ->and($report)->toContain('validation-retention-planned / runtime-still-blocked')
        ->and($report)->toContain('Wave 68A — Manual Distribution External Evidence Validation Plan')
        ->and($report)->toContain('Wave 68B — Manual Distribution External Evidence Retention Plan')
        ->and($report)->toContain('Final request/response contract required before routes.')
        ->and($report)->toContain('Explicit rejected values.')
        ->and($report)->toContain('do-not-store-until-retention-policy-is-approved')
        ->and($report)->toContain('Purge rules.')
        ->and($report)->toContain('Review, rejection, correction, and escalation workflow.')
        ->and($report)->toContain('Database schema decision.')
        ->and($report)->toContain('Evidence request classes.')
        ->and($report)->toContain('Evidence upload controls.')
        ->and($report)->toContain('Evidence purge jobs.')
        ->and($report)->toContain('Journal handoff persistence.')
        ->and($report)->toContain('checked 59')
        ->and($report)->toContain('ok 59')
        ->and($report)->toContain('stale 0')
        ->and($report)->toContain('Cockpit Wave 69 — Manual Distribution External Evidence Review / Handoff Plan')
        ->and($cockpitCompass)->toContain('Cockpit Wave 68 — Manual Distribution External Evidence Validation / Retention Closure')
        ->and($cockpitCompass)->toContain('reports/384-wave-68-external-evidence-validation-retention-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 68 — Manual Distribution External Evidence Validation / Retention Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/384-wave-68-external-evidence-validation-retention-closure.md');
});
