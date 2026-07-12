<?php

declare(strict_types=1);

it('documents cockpit wave 58 beneficiary url copy acceptance closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/352-wave-58-beneficiary-url-copy-acceptance-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 58 — Beneficiary URL Copy Acceptance Closure')
        ->and($report)->toContain('Final Acceptance Result')
        ->and($report)->toContain('Pass')
        ->and($report)->toContain('Pay Code tested: 6LGM')
        ->and($report)->toContain('http://x-change-sandbox.test/x/claim/6LGM/experience')
        ->and($report)->toContain('checked: 59')
        ->and($report)->toContain('Cockpit Wave 59 — Manual Distribution Link Operational Guidance / Operator Help Text')
        ->and($cockpitCompass)->toContain('Cockpit Wave 58 — Beneficiary URL Copy Acceptance Closure')
        ->and($cockpitCompass)->toContain('reports/352-wave-58-beneficiary-url-copy-acceptance-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 58 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/352-wave-58-beneficiary-url-copy-acceptance-closure.md');
});
