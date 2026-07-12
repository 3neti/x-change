<?php

declare(strict_types=1);

it('documents the pay code detail distribution full url continuity audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/332-wave-54a-pay-code-detail-distribution-full-url-continuity-audit.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $props = file_get_contents($packageRoot.'/src/Support/Cockpit/CockpitReadOnlyPageProps.php');
    $routes = file_get_contents($packageRoot.'/routes/web.php');

    expect($report)
        ->toContain('Cockpit Wave 54A')
        ->toContain('distribution_links')
        ->toContain('redeem_url')
        ->toContain('redeem_path')
        ->toContain('x-change.claim.experience')
        ->toContain('This is read-only link presentation')
        ->and($props)
        ->toContain('toVoucherDetailArray')
        ->toContain('toDistributionWorkspaceArray')
        ->and($routes)
        ->toContain("name('x-change.claim.experience')");
});
