<?php

declare(strict_types=1);

it('documents cockpit wave 33 distribution workspace share surface closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/230-wave-33-distribution-workspace-share-surface-closure.md');

    expect($report)->toContain('Cockpit Wave 33 — Distribution Workspace Share Surface Closure')
        ->and($report)->toContain('Wave 33A')
        ->and($report)->toContain('Wave 33B')
        ->and($report)->toContain('Wave 33C')
        ->and($report)->toContain('Wave 33D')
        ->and($report)->toContain('Wave 33E')
        ->and($report)->toContain('copy-text readiness')
        ->and($report)->toContain('Playwright')
        ->and($report)->toContain('Cockpit Wave 34 — Quick Generate Post-Issuance Navigation / Share Handoff');
});

it('records cockpit wave 33 closure in the cockpit and settlement compasses', function () {
    $packageRoot = dirname(__DIR__, 3);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('reports/230-wave-33-distribution-workspace-share-surface-closure.md')
        ->and($cockpitCompass)->toContain('Cockpit Wave 34 — Quick Generate Post-Issuance Navigation / Share Handoff')
        ->and($settlementCompass)->toContain('Cockpit Wave 33 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/230-wave-33-distribution-workspace-share-surface-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 34 — Quick Generate Post-Issuance Navigation / Share Handoff');
});
