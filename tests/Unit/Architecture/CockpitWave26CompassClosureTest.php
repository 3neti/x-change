<?php

declare(strict_types=1);

it('records cockpit wave 26 closure in the cockpit and settlement os compasses', function () {
    $packageRoot = dirname(__DIR__, 3);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('Cockpit Wave 26 — Operator Activity Filter Manual Browser Acceptance / Query UX Hardening')
        ->and($cockpitCompass)->toContain('reports/198-wave-26-operator-activity-filter-browser-acceptance.md')
        ->and($cockpitCompass)->toContain('No activity matches current filters')
        ->and($cockpitCompass)->toContain('checked 58, ok 58, stale 0')
        ->and($cockpitCompass)->toContain('Cockpit Wave 27 — Operator Activity Filter UX Refinement / Multi-Select Decision')
        ->and($settlementCompass)->toContain('Cockpit Wave 26 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/198-wave-26-operator-activity-filter-browser-acceptance.md')
        ->and($settlementCompass)->toContain('explicit no-match copy')
        ->and($settlementCompass)->toContain('no mutation/provider/wallet/journal/action/feedback execution scope changed')
        ->and($settlementCompass)->toContain('Cockpit Wave 27 — Operator Activity Filter UX Refinement / Multi-Select Decision');
});
