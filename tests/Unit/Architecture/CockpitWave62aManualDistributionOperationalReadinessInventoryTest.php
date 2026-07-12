<?php

declare(strict_types=1);

it('documents cockpit wave 62a manual distribution operational readiness inventory', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/364-wave-62a-manual-distribution-operational-readiness-inventory.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 62A — Manual Distribution Operational Readiness Inventory')
        ->and($report)->toContain('Voucher Detail displays the canonical beneficiary Pay Code URL.')
        ->and($report)->toContain('Distribution Workspace displays the canonical beneficiary Pay Code URL.')
        ->and($report)->toContain('Both surfaces expose browser-local copy controls when a beneficiary URL is available.')
        ->and($report)->toContain('Both surfaces show accepted manual distribution guidance.')
        ->and($report)->toContain('Operators may manually copy the URL and share it through an approved external workflow.')
        ->and($report)->toContain('Operators must verify the recipient before sharing.')
        ->and($report)->toContain('Pay Code: 6LGM')
        ->and($report)->toContain('Beneficiary URL: http://x-change-sandbox.test/x/claim/6LGM/experience')
        ->and($report)->toContain('SMS delivery.')
        ->and($report)->toContain('Campaign dispatch.')
        ->and($report)->toContain('Copy telemetry persistence.')
        ->and($report)->toContain('Short-link generation.')
        ->and($report)->toContain('QR asset generation.')
        ->and($report)->toContain('manual-copy-operational / automated-distribution-not-authorized')
        ->and($report)->toContain('Cockpit Wave 62B — Manual Distribution Next Capability Decision Matrix')
        ->and($cockpitCompass)->toContain('Cockpit Wave 62A — Manual Distribution Operational Readiness Inventory')
        ->and($cockpitCompass)->toContain('reports/364-wave-62a-manual-distribution-operational-readiness-inventory.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 62A — Manual Distribution Operational Readiness Inventory')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/364-wave-62a-manual-distribution-operational-readiness-inventory.md');
});
