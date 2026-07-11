<?php

declare(strict_types=1);

it('documents cockpit wave 31 row action runtime parity closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/218-wave-31-pay-code-explorer-row-action-runtime-parity-closure.md');

    expect($report)->toContain('Cockpit Wave 31 — Pay Code Explorer Row Action Runtime Parity Closure')
        ->and($report)->toContain('View details')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}')
        ->and($report)->toContain('Distribution')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}/distribution')
        ->and($report)->toContain('Playwright browser smoke')
        ->and($report)->toContain('Cockpit Wave 32 — Voucher Detail Functional Parity / Evidence Surface Hardening');
});

it('records cockpit wave 31 closure in the cockpit and settlement compasses', function () {
    $packageRoot = dirname(__DIR__, 3);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('reports/218-wave-31-pay-code-explorer-row-action-runtime-parity-closure.md')
        ->and($cockpitCompass)->toContain('Cockpit Wave 32 — Voucher Detail Functional Parity / Evidence Surface Hardening')
        ->and($settlementCompass)->toContain('Cockpit Wave 31 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/218-wave-31-pay-code-explorer-row-action-runtime-parity-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 32 — Voucher Detail Functional Parity / Evidence Surface Hardening');
});
