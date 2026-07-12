<?php

declare(strict_types=1);

it('documents cockpit wave 54d distribution workspace full url presentation', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/335-wave-54d-distribution-workspace-full-url-presentation.md';
    $pagePath = $packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue';
    $typesPath = $packageRoot.'/resources/js/cockpit/types.ts';
    $frontendTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $page = file_get_contents($pagePath);
    $types = file_get_contents($typesPath);
    $frontendTest = file_get_contents($frontendTestPath);

    expect($report)->toContain('Cockpit Wave 54D — Distribution Workspace Full URL Presentation')
        ->and($report)->toContain('Beneficiary Pay Code URL')
        ->and($report)->toContain('delivery disabled')
        ->and($report)->toContain('Cockpit Wave 54E — Full URL Destination Publish / Drift Verification')
        ->and($page)->toContain('cockpit-distribution-workspace-links-panel')
        ->and($page)->toContain('cockpit-distribution-workspace-beneficiary-url-link')
        ->and($page)->toContain('Beneficiary Pay Code URL')
        ->and($page)->toContain('delivery disabled')
        ->and($types)->toContain('distribution_links?: Record<string, unknown>;')
        ->and($frontendTest)->toContain('https://example.test/x/claim/PC-DIST-001/experience')
        ->and($frontendTest)->toContain('cockpit-distribution-workspace-beneficiary-url-link');
});
