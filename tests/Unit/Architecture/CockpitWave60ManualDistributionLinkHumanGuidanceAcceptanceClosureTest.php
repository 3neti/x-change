<?php

declare(strict_types=1);

it('documents cockpit wave 60 manual distribution link human guidance acceptance closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/360-wave-60-manual-distribution-link-human-guidance-acceptance-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 60 — Manual Distribution Link Human Guidance Acceptance Closure')
        ->and($report)->toContain('Complete / `pending-human-guidance-intake`.')
        ->and($report)->toContain('Cockpit Wave 60A — Manual Distribution Link Human Guidance Acceptance Plan.')
        ->and($report)->toContain('Cockpit Wave 60B — Manual Guidance Human Evidence Record Template.')
        ->and($report)->toContain('Cockpit Wave 60C — Manual Guidance Acceptance Decision Policy.')
        ->and($report)->toContain('Cockpit Wave 60D — Manual Guidance Pending Acceptance Status / Closure.')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}/distribution')
        ->and($report)->toContain('checked 59, ok 59, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Wave 60 added documentation and architecture guards only.')
        ->and($report)->toContain('Cockpit Wave 61 — Manual Distribution Guidance Human Evidence Intake / Acceptance Decision')
        ->and($cockpitCompass)->toContain('Cockpit Wave 60 — Manual Distribution Link Human Guidance Acceptance Closure')
        ->and($cockpitCompass)->toContain('reports/360-wave-60-manual-distribution-link-human-guidance-acceptance-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 60 — Manual Distribution Link Human Guidance Acceptance Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/360-wave-60-manual-distribution-link-human-guidance-acceptance-closure.md');
});
