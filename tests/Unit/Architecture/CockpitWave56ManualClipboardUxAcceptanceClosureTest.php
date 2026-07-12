<?php

declare(strict_types=1);

it('documents cockpit wave 56 manual clipboard ux acceptance closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/345-wave-56-manual-clipboard-ux-acceptance-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 56 — Manual Clipboard UX Acceptance Closure')
        ->and($report)->toContain('manual copy success state')
        ->and($report)->toContain('clipboard rejection failed state')
        ->and($report)->toContain('no copy path calls `fetch`')
        ->and($report)->toContain('checked: 59')
        ->and($report)->toContain('stale: 0')
        ->and($report)->toContain('Cockpit Wave 57 — Beneficiary URL Copy Human Acceptance Intake')
        ->and($cockpitCompass)->toContain('Cockpit Wave 56 — Manual Clipboard UX Acceptance Closure')
        ->and($cockpitCompass)->toContain('reports/345-wave-56-manual-clipboard-ux-acceptance-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 56 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/345-wave-56-manual-clipboard-ux-acceptance-closure.md');
});
