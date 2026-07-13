<?php

declare(strict_types=1);

it('documents cockpit wave 67 external evidence authorization redaction closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/381-wave-67-external-evidence-authorization-redaction-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 67 — Manual Distribution External Evidence Authorization / Redaction Closure')
        ->and($report)->toContain('Complete / Authorization and redaction planning complete; runtime remains blocked.')
        ->and($report)->toContain('authorization-redaction-planned / runtime-still-blocked')
        ->and($report)->toContain('Wave 67A — Manual Distribution External Evidence Authorization Plan')
        ->and($report)->toContain('Wave 67B — Manual Distribution External Evidence Redaction Plan')
        ->and($report)->toContain('Evidence create permission.')
        ->and($report)->toContain('Evidence review permission.')
        ->and($report)->toContain('Tenant scope.')
        ->and($report)->toContain('Recipient mobile/email/reference values.')
        ->and($report)->toContain('Credentials and tokens.')
        ->and($report)->toContain('Validation contract.')
        ->and($report)->toContain('Retention and purge policy.')
        ->and($report)->toContain('Journal handoff policy.')
        ->and($report)->toContain('x-feedback correlation policy.')
        ->and($report)->toContain('Evidence forms.')
        ->and($report)->toContain('Upload controls.')
        ->and($report)->toContain('Migrations.')
        ->and($report)->toContain('DTOs.')
        ->and($report)->toContain('checked 59')
        ->and($report)->toContain('ok 59')
        ->and($report)->toContain('stale 0')
        ->and($report)->toContain('Cockpit Wave 68 — Manual Distribution External Evidence Validation / Retention Plan')
        ->and($cockpitCompass)->toContain('Cockpit Wave 67 — Manual Distribution External Evidence Authorization / Redaction Closure')
        ->and($cockpitCompass)->toContain('reports/381-wave-67-external-evidence-authorization-redaction-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 67 — Manual Distribution External Evidence Authorization / Redaction Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/381-wave-67-external-evidence-authorization-redaction-closure.md');
});
