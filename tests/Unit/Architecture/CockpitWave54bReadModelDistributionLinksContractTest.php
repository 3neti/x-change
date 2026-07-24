<?php

declare(strict_types=1);

it('documents the read model distribution links contract', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/333-wave-54b-read-model-distribution-links-contract.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $voucherData = file_get_contents($packageRoot.'/src/Data/Cockpit/CockpitVoucherReadModelData.php');
    $distributionData = file_get_contents($packageRoot.'/src/Data/Cockpit/CockpitDistributionWorkspaceReadModelData.php');
    $providerTest = file_get_contents($packageRoot.'/tests/Unit/Cockpit/VoucherLifecycleDistributionLinksReadModelTest.php');

    expect($report)
        ->toContain('Cockpit Wave 54B')
        ->toContain('read_model.voucher.distribution_links')
        ->toContain('distribution_workspace_read_model.distribution_links')
        ->toContain('x-change.claim.show')
        ->and($voucherData)
        ->toContain('public readonly array $distribution_links')
        ->and($distributionData)
        ->toContain('public readonly array $distribution_links')
        ->and($providerTest)
        ->toContain('adds read only distribution links to voucher detail read models')
        ->toContain('http://localhost/x/claim/PC-WAVE-54B');
});
