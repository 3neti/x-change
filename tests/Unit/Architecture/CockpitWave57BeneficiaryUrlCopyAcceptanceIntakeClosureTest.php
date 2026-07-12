<?php

declare(strict_types=1);

it('documents cockpit wave 57 beneficiary url copy acceptance intake closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/349-wave-57-beneficiary-url-copy-acceptance-intake-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 57 — Beneficiary URL Copy Acceptance Intake Closure')
        ->and($report)->toContain('pending-human-intake')
        ->and($report)->toContain('closes the acceptance-intake scaffold, not the human acceptance itself')
        ->and($report)->toContain('checked: 59')
        ->and($report)->toContain('stale: 0')
        ->and($report)->toContain('Cockpit Wave 58 — Beneficiary URL Copy Human Evidence Intake / Acceptance Decision')
        ->and($cockpitCompass)->toContain('Cockpit Wave 57 — Beneficiary URL Copy Acceptance Intake Closure')
        ->and($cockpitCompass)->toContain('reports/349-wave-57-beneficiary-url-copy-acceptance-intake-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 57 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/349-wave-57-beneficiary-url-copy-acceptance-intake-closure.md');
});
