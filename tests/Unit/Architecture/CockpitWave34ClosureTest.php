<?php

declare(strict_types=1);

it('documents cockpit wave 34 quick generate post issuance navigation closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/236-wave-34-post-issuance-navigation-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 34')
        ->toContain('Open Cockpit detail')
        ->toContain('Open Distribution workspace')
        ->toContain('Automatic redirect: disabled')
        ->toContain('Playwright verifies')
        ->toContain('Boundaries Preserved')
        ->toContain('Cockpit Wave 35 — Campaign Context Quick Generate Adoption');

    expect($cockpitCompass)
        ->toContain('reports/236-wave-34-post-issuance-navigation-closure.md')
        ->toContain('Cockpit Wave 35 — Campaign Context Quick Generate Adoption');

    expect($settlementCompass)
        ->toContain('../ui-cockpit/reports/236-wave-34-post-issuance-navigation-closure.md')
        ->toContain('Cockpit Wave 35 — Campaign Context Quick Generate Adoption');
});
