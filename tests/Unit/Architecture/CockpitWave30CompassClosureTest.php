<?php

declare(strict_types=1);

it('records cockpit wave 30 closure in the cockpit and settlement os compasses', function () {
    $packageRoot = dirname(__DIR__, 3);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('Cockpit Wave 30 — Pay Code Explorer Functional Read Model Parity / Legacy Index Comparison')
        ->and($cockpitCompass)->toContain('reports/212-wave-30-pay-code-explorer-functional-parity-closure.md')
        ->and($cockpitCompass)->toContain('search/status GET filters')
        ->and($cockpitCompass)->toContain('Cockpit Wave 31 — Pay Code Explorer Detail Navigation / Row Action Runtime Parity')
        ->and($settlementCompass)->toContain('Cockpit Wave 30 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/212-wave-30-pay-code-explorer-functional-parity-closure.md')
        ->and($settlementCompass)->toContain('read-only search/status filters')
        ->and($settlementCompass)->toContain('Cockpit Wave 31 — Pay Code Explorer Detail Navigation / Row Action Runtime Parity');
});
