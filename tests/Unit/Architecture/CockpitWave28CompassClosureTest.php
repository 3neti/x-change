<?php

declare(strict_types=1);

it('records cockpit wave 28 closure in the cockpit and settlement os compasses', function () {
    $packageRoot = dirname(__DIR__, 3);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('Cockpit Wave 28 — Operator Activity Filter Browser Acceptance / Next Runtime Decision')
        ->and($cockpitCompass)->toContain('reports/201-wave-28b-operator-activity-filter-browser-acceptance.md')
        ->and($cockpitCompass)->toContain('reports/202-wave-28c-operator-activity-next-runtime-decision.md')
        ->and($cockpitCompass)->toContain('reports/203-wave-28-operator-activity-filter-acceptance-closure.md')
        ->and($cockpitCompass)->toContain('Dusk activity filter smoke: 1 passed, 27 assertions')
        ->and($cockpitCompass)->toContain('Cockpit Wave 29 — Pay Code Explorer Runtime Parity / Activity Navigation Bridge')
        ->and($settlementCompass)->toContain('Cockpit Wave 28 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/203-wave-28-operator-activity-filter-acceptance-closure.md')
        ->and($settlementCompass)->toContain('Close the Operator Activity filter hardening sequence for now')
        ->and($settlementCompass)->toContain('Cockpit Wave 29 — Pay Code Explorer Runtime Parity / Activity Navigation Bridge');
});
