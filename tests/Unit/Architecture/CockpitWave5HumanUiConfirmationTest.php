<?php

declare(strict_types=1);

it('documents cockpit wave 5 human ui confirmation', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/110-wave-5-human-ui-confirmation.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5 — Human UI Confirmation')
        ->and($report)->toContain('Status: Pass — accepted by human')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('Real `MCPC` activity is visible.')
        ->and($report)->toContain('BrickMath diagnostic `YEZA` activity is absent.')
        ->and($report)->toContain('Synthetic `PC-LOCAL-DIAGNOSTIC` fixture is absent.')
        ->and($report)->toContain('No raw payloads, secrets, retry controls, or new mutation controls')
        ->and($report)->toContain('Correlation: corr-cockpit-real-activity-5b')
        ->and($report)->toContain('Cockpit Mutation Wave 6 — Production Hardening Plan')
        ->and($report)->toContain('Wave 6A — Durable Activity Authorization / Tenant Scope Decision')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5 — Human UI Confirmation')
        ->and($cockpitCompass)->toContain('reports/110-wave-5-human-ui-confirmation.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 5 — Human UI Confirmation')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/110-wave-5-human-ui-confirmation.md');
});
