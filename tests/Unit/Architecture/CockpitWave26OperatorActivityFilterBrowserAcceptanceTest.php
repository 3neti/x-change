<?php

declare(strict_types=1);

it('documents cockpit wave 26 operator activity filter browser acceptance', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/198-wave-26-operator-activity-filter-browser-acceptance.md');

    expect($report)->toContain('/x/cockpit?activity_search=PC-DUSK-FILTER&activity_status=issued&activity_handoff_status=recorded')
        ->and($report)->toContain('Showing 1 matching activity for the current read-only filters.')
        ->and($report)->toContain('No activity matches current filters')
        ->and($report)->toContain('tests/Browser/CockpitDashboardActivityFilterSmokeTest.php')
        ->and($report)->toContain('Dusk activity filter smoke: 1 passed, 23 assertions')
        ->and($report)->toContain('Frontend hydration: 18 passed')
        ->and($report)->toContain('Asset drift doctor: checked 58, ok 58, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Enable handoffs')
        ->and($report)->toContain('Save configuration')
        ->and($report)->toContain('provider_payload')
        ->and($report)->toContain('wallet_payload')
        ->and($report)->toContain('Cockpit Wave 27 — Operator Activity Filter UX Refinement / Multi-Select Decision');
});
