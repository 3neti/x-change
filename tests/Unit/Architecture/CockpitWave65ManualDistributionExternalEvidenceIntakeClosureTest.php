<?php

declare(strict_types=1);

it('documents cockpit wave 65 manual distribution external evidence intake closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/375-wave-65-manual-distribution-external-evidence-intake-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 65 — Manual Distribution External Evidence Intake Closure')
        ->and($report)->toContain('Complete / Planning-only evidence intake baseline recorded.')
        ->and($report)->toContain('No runtime intake was added.')
        ->and($report)->toContain('Cockpit Wave 65A — Manual Distribution External Evidence Intake Decision.')
        ->and($report)->toContain('Cockpit Wave 65B — Manual Distribution External Evidence Schema / Template.')
        ->and($report)->toContain('planning-only / no-intake-runtime')
        ->and($report)->toContain('Approved external workflow used.')
        ->and($report)->toContain('Redacted delivery reference.')
        ->and($report)->toContain('Wave 65 did not add:')
        ->and($report)->toContain('Evidence persistence.')
        ->and($report)->toContain('Journal records.')
        ->and($report)->toContain('Feedback records.')
        ->and($report)->toContain('Future evidence intake must not become lifecycle truth')
        ->and($report)->toContain('checked 59, ok 59, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Cockpit Wave 66 — Manual Distribution External Evidence Runtime Decision')
        ->and($cockpitCompass)->toContain('Cockpit Wave 65 — Manual Distribution External Evidence Intake Closure')
        ->and($cockpitCompass)->toContain('reports/375-wave-65-manual-distribution-external-evidence-intake-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 65 — Manual Distribution External Evidence Intake Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/375-wave-65-manual-distribution-external-evidence-intake-closure.md');
});
