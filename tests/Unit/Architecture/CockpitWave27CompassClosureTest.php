<?php

declare(strict_types=1);

it('records cockpit wave 27 closure in the cockpit and settlement os compasses', function () {
    $packageRoot = dirname(__DIR__, 3);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('Cockpit Wave 27 — Operator Activity Filter UX Refinement / Multi-Select Decision')
        ->and($cockpitCompass)->toContain('reports/199-wave-27a-operator-activity-filter-multiselect-decision.md')
        ->and($cockpitCompass)->toContain('reports/200-wave-27-operator-activity-filter-ux-refinement-closure.md')
        ->and($cockpitCompass)->toContain('Clear search')
        ->and($cockpitCompass)->toContain('Cockpit Wave 28 — Operator Activity Filter Browser Acceptance / Next Runtime Decision')
        ->and($settlementCompass)->toContain('Cockpit Wave 27 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/200-wave-27-operator-activity-filter-ux-refinement-closure.md')
        ->and($settlementCompass)->toContain('visible multi-select controls remain deferred')
        ->and($settlementCompass)->toContain('no mutation/provider/wallet/journal/action/feedback execution scope changed')
        ->and($settlementCompass)->toContain('Cockpit Wave 28 — Operator Activity Filter Browser Acceptance / Next Runtime Decision');
});
