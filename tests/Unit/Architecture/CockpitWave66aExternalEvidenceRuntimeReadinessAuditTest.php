<?php

declare(strict_types=1);

it('documents cockpit wave 66a external evidence runtime readiness audit', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/376-wave-66a-external-evidence-runtime-readiness-audit.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 66A — Manual Distribution External Evidence Runtime Readiness Audit')
        ->and($report)->toContain('not-ready-for-runtime')
        ->and($report)->toContain('Authorization policy for who may submit evidence.')
        ->and($report)->toContain('Tenant and operator scope rules.')
        ->and($report)->toContain('Redaction rules for recipient references and delivery references.')
        ->and($report)->toContain('Evidence retention and purge policy.')
        ->and($report)->toContain('Journal handoff policy.')
        ->and($report)->toContain('x-feedback correlation policy.')
        ->and($report)->toContain('Do not scaffold runtime intake yet.')
        ->and($report)->toContain('This audit does not create:')
        ->and($report)->toContain('Cockpit Wave 66B — Manual Distribution External Evidence Runtime Preconditions')
        ->and($cockpitCompass)->toContain('Cockpit Wave 66A — Manual Distribution External Evidence Runtime Readiness Audit')
        ->and($cockpitCompass)->toContain('reports/376-wave-66a-external-evidence-runtime-readiness-audit.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 66A — Manual Distribution External Evidence Runtime Readiness Audit')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/376-wave-66a-external-evidence-runtime-readiness-audit.md');
});
