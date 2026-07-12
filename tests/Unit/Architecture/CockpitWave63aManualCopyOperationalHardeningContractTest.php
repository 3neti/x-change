<?php

declare(strict_types=1);

it('documents cockpit wave 63a manual copy operational hardening contract', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/367-wave-63a-manual-copy-operational-hardening-contract.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 63A — Manual Copy Operational Hardening Contract')
        ->and($report)->toContain('Browser-local.')
        ->and($report)->toContain('Non-persistent.')
        ->and($report)->toContain('Non-delivery.')
        ->and($report)->toContain('Non-telemetry.')
        ->and($report)->toContain('Successful copy writes only to `navigator.clipboard.writeText`.')
        ->and($report)->toContain('Copy does not call `fetch`.')
        ->and($report)->toContain('Copy does not call `navigator.sendBeacon`.')
        ->and($report)->toContain('Copy does not create or use `XMLHttpRequest`.')
        ->and($report)->toContain('Voucher Detail.')
        ->and($report)->toContain('Distribution Workspace.')
        ->and($report)->toContain('Wave 63 does not add:')
        ->and($report)->toContain('Cockpit Wave 63B — Manual Copy No-Backend-Interaction Regression Guard')
        ->and($cockpitCompass)->toContain('Cockpit Wave 63A — Manual Copy Operational Hardening Contract')
        ->and($cockpitCompass)->toContain('reports/367-wave-63a-manual-copy-operational-hardening-contract.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 63A — Manual Copy Operational Hardening Contract')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/367-wave-63a-manual-copy-operational-hardening-contract.md');
});
