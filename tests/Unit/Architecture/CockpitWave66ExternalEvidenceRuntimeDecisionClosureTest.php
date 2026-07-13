<?php

declare(strict_types=1);

it('documents cockpit wave 66 external evidence runtime decision closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/378-wave-66-external-evidence-runtime-decision-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 66 — Manual Distribution External Evidence Runtime Decision Closure')
        ->and($report)->toContain('Complete / Runtime blocked until preconditions are explicitly approved.')
        ->and($report)->toContain('runtime-blocked / preconditions-required')
        ->and($report)->toContain('Wave 66A — Manual Distribution External Evidence Runtime Readiness Audit')
        ->and($report)->toContain('Wave 66B — Manual Distribution External Evidence Runtime Preconditions')
        ->and($report)->toContain('Authorization policy for who may create evidence.')
        ->and($report)->toContain('Tenant and operator scope rules.')
        ->and($report)->toContain('Redaction policy for recipient references, delivery references, notes, and attachments.')
        ->and($report)->toContain('Journal handoff policy.')
        ->and($report)->toContain('x-feedback correlation policy.')
        ->and($report)->toContain('x-action continuation policy.')
        ->and($report)->toContain('x-campaign attribution policy.')
        ->and($report)->toContain('Evidence forms.')
        ->and($report)->toContain('Routes.')
        ->and($report)->toContain('Controllers.')
        ->and($report)->toContain('Migrations.')
        ->and($report)->toContain('Models.')
        ->and($report)->toContain('DTOs.')
        ->and($report)->toContain('checked 59')
        ->and($report)->toContain('ok 59')
        ->and($report)->toContain('stale 0')
        ->and($report)->toContain('Cockpit Wave 67 — Manual Distribution External Evidence Authorization / Redaction Plan')
        ->and($cockpitCompass)->toContain('Cockpit Wave 66 — Manual Distribution External Evidence Runtime Decision Closure')
        ->and($cockpitCompass)->toContain('reports/378-wave-66-external-evidence-runtime-decision-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 66 — Manual Distribution External Evidence Runtime Decision Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/378-wave-66-external-evidence-runtime-decision-closure.md');
});
