<?php

declare(strict_types=1);

it('documents cockpit wave 56b automated clipboard ux evidence guard', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/343-wave-56b-automated-clipboard-ux-evidence-guard.md';
    $manualCopyTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitManualCopyButton.test.ts';
    $voucherDetailTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts';
    $distributionTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $manualCopyTest = file_get_contents($manualCopyTestPath);
    $voucherDetailTest = file_get_contents($voucherDetailTestPath);
    $distributionTest = file_get_contents($distributionTestPath);

    expect($report)->toContain('Cockpit Wave 56B — Automated Clipboard UX Evidence Guard')
        ->and($report)->toContain('clipboard rejection / failed state')
        ->and($report)->toContain('no `fetch` backend calls')
        ->and($report)->toContain('Cockpit Wave 56C — Human Clipboard UX Evidence Record Template')
        ->and($manualCopyTest)->toContain('shows failed state when clipboard write rejects without backend interaction')
        ->and($manualCopyTest)->toContain('Copy failed')
        ->and($manualCopyTest)->toContain('No backend call was made')
        ->and($voucherDetailTest)->toContain('copies the Voucher Detail beneficiary URL through the browser clipboard only')
        ->and($distributionTest)->toContain('copies the Distribution Workspace beneficiary URL through the browser clipboard only');
});
