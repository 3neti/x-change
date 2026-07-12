<?php

declare(strict_types=1);

it('documents cockpit wave 59a manual distribution link operational guidance contract', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/353-wave-59a-manual-distribution-link-operational-guidance-contract.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Cockpit Wave 59A — Manual Distribution Link Operational Guidance Contract')
        ->and($report)->toContain('manual distribution only')
        ->and($report)->toContain('approved external workflow')
        ->and($report)->toContain('does not record copy telemetry')
        ->and($report)->toContain('sensitive settlement access material')
        ->and($report)->toContain('Voucher Detail beneficiary URL panel')
        ->and($report)->toContain('Distribution Workspace beneficiary URL panel')
        ->and($report)->toContain('Cockpit Wave 59B — Voucher Detail Operational Guidance Text');
});
