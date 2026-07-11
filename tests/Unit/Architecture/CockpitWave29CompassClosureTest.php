<?php

declare(strict_types=1);

it('records cockpit wave 29 closure in the cockpit and settlement os compasses', function () {
    $packageRoot = dirname(__DIR__, 3);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('Cockpit Wave 29 — Pay Code Explorer Runtime Parity / Activity Navigation Bridge')
        ->and($cockpitCompass)->toContain('reports/204-wave-29a-pay-code-explorer-runtime-parity-audit.md')
        ->and($cockpitCompass)->toContain('reports/205-wave-29-pay-code-explorer-activity-bridge-closure.md')
        ->and($cockpitCompass)->toContain('Open in Explorer')
        ->and($cockpitCompass)->toContain('Dusk bridge smoke: 1 passed, 22 assertions')
        ->and($cockpitCompass)->toContain('Cockpit Wave 30 — Pay Code Explorer Functional Read Model Parity / Legacy Index Comparison')
        ->and($settlementCompass)->toContain('Cockpit Wave 29 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/205-wave-29-pay-code-explorer-activity-bridge-closure.md')
        ->and($settlementCompass)->toContain('Operator activity cards can now bridge into Pay Code Explorer')
        ->and($settlementCompass)->toContain('Cockpit Wave 30 — Pay Code Explorer Functional Read Model Parity / Legacy Index Comparison');
});
