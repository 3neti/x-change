<?php

declare(strict_types=1);

it('records cockpit wave 23 closure in the cockpit and settlement os compasses', function () {
    $packageRoot = dirname(__DIR__, 3);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('Current slice: Cockpit Wave 23 — Runtime Profile Operator Acceptance Closure / Next Runtime Decision')
        ->and($cockpitCompass)->toContain('Status: Complete / runtime profile accepted; next runtime decision recorded')
        ->and($cockpitCompass)->toContain('reports/194-wave-23-runtime-profile-operator-acceptance-closure.md')
        ->and($cockpitCompass)->toContain('reports/195-wave-23-next-runtime-decision-record.md')
        ->and($cockpitCompass)->toContain('runtime configuration mutation is not authorized from Cockpit')
        ->and($cockpitCompass)->toContain('Cockpit Wave 24 — Operator Activity Search / Filter Runtime Readiness')
        ->and($settlementCompass)->toContain('Cockpit Wave 23 — Runtime Profile Operator Acceptance Closure / Next Runtime Decision')
        ->and($settlementCompass)->toContain('Runtime Profile is accepted as a read-only operator diagnostics surface')
        ->and($settlementCompass)->toContain('runtime mutation remains blocked')
        ->and($settlementCompass)->toContain('Cockpit Wave 24 — Operator Activity Search / Filter Runtime Readiness');
});
