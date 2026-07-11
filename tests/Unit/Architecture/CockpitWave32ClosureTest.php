<?php

declare(strict_types=1);

it('documents cockpit wave 32 voucher detail evidence surface closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/224-wave-32-voucher-detail-evidence-surface-closure.md');

    expect($report)->toContain('Cockpit Wave 32 — Voucher Detail Evidence Surface Closure')
        ->and($report)->toContain('Wave 32A')
        ->and($report)->toContain('Wave 32B')
        ->and($report)->toContain('Wave 32C')
        ->and($report)->toContain('Wave 32D')
        ->and($report)->toContain('Wave 32E')
        ->and($report)->toContain('Evidence summary')
        ->and($report)->toContain('Playwright')
        ->and($report)->toContain('Cockpit Wave 33 — Distribution Workspace Functional Parity / Share Surface Hardening');
});

it('records cockpit wave 32 closure in the cockpit and settlement compasses', function () {
    $packageRoot = dirname(__DIR__, 3);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('reports/224-wave-32-voucher-detail-evidence-surface-closure.md')
        ->and($cockpitCompass)->toContain('Cockpit Wave 33 — Distribution Workspace Functional Parity / Share Surface Hardening')
        ->and($settlementCompass)->toContain('Cockpit Wave 32 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/224-wave-32-voucher-detail-evidence-surface-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 33 — Distribution Workspace Functional Parity / Share Surface Hardening');
});
