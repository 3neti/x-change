<?php

declare(strict_types=1);

it('documents cockpit wave 55d distribution workspace manual copy adoption', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/340-wave-55d-distribution-workspace-manual-copy-adoption.md';
    $pagePath = $packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue';
    $frontendTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $page = file_get_contents($pagePath);
    $frontendTest = file_get_contents($frontendTestPath);

    expect($report)->toContain('Cockpit Wave 55D — Distribution Workspace Manual Copy Adoption')
        ->and($report)->toContain('does not call `fetch`')
        ->and($report)->toContain('Cockpit Wave 55E — Manual Copy Publish / Drift Verification Closure')
        ->and($page)->toContain('CockpitManualCopyButton')
        ->and($page)->toContain('Copy beneficiary URL')
        ->and($frontendTest)->toContain('copies the Distribution Workspace beneficiary URL through the browser clipboard only')
        ->and($frontendTest)->toContain('not.toHaveBeenCalled');
});
