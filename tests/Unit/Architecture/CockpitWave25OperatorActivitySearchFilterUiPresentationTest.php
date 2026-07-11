<?php

declare(strict_types=1);

it('documents cockpit wave 25 operator activity search filter ui presentation', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/197-wave-25-operator-activity-search-filter-ui-presentation.md');

    expect($report)->toContain('activity_search')
        ->and($report)->toContain('activity_status')
        ->and($report)->toContain('activity_handoff_status')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityPanel')
        ->and($report)->toContain('GET /x/cockpit')
        ->and($report)->toContain('tests/Browser/CockpitDashboardActivityFilterSmokeTest.php')
        ->and($report)->toContain('runtime configuration mutation UI')
        ->and($report)->toContain('raw payload display')
        ->and($report)->toContain('Operators should now see a read-only filter bar')
        ->and($report)->toContain('Dusk activity filter smoke: 1 passed, 23 assertions')
        ->and($report)->toContain('Asset drift doctor: checked 58, ok 58, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Cockpit Wave 26 — Operator Activity Filter Manual Browser Acceptance / Query UX Hardening');
});
