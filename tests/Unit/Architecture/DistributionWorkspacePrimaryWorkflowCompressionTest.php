<?php

declare(strict_types=1);

it('documents distribution workspace primary workflow compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/619-distribution-workspace-primary-workflow-compression-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');

    expect($report)
        ->toContain('Distribution Workspace Primary Workflow Compression — Slice 1')
        ->toContain('compact operational header')
        ->toContain('Presentation-only shell compression')
        ->and($page)->toContain('data-testid="cockpit-distribution-workspace-header"')
        ->and($page)->toContain('data-testid="cockpit-distribution-workspace-header-facts"')
        ->and($page)->toContain('data-testid="cockpit-distribution-workspace-boundary"')
        ->and($page)->toContain('Workspace rules')
        ->and($frontendTest)->toContain('renders the workspace shell as a compact operational header');
});
