<?php

declare(strict_types=1);

it('documents cockpit wave 71 external evidence runtime readiness closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/387-wave-71-external-evidence-runtime-readiness-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 71 — Manual Distribution External Evidence Runtime Readiness Closure')
        ->and($report)->toContain('not-runtime-ready / defer-runtime-implementation')
        ->and($report)->toContain('Runtime readiness audit.')
        ->and($report)->toContain('Attachment/storage decision.')
        ->and($report)->toContain('No approved database schema.')
        ->and($report)->toContain('No approved journal handoff adapter.')
        ->and($report)->toContain('structured redacted text-only external evidence intake')
        ->and($report)->toContain('Raw beneficiary URLs in notes.')
        ->and($report)->toContain('Direct campaign mutation.')
        ->and($report)->toContain('checked 59')
        ->and($report)->toContain('ok 59')
        ->and($report)->toContain('Cockpit Wave 72 — Manual Distribution External Evidence Runtime Implementation Decision')
        ->and($cockpitCompass)->toContain('Cockpit Wave 71 — Manual Distribution External Evidence Runtime Readiness Closure')
        ->and($cockpitCompass)->toContain('reports/387-wave-71-external-evidence-runtime-readiness-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 71 — Manual Distribution External Evidence Runtime Readiness Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/387-wave-71-external-evidence-runtime-readiness-closure.md');
});
