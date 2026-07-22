<?php

declare(strict_types=1);

it('documents distribution workspace lower panel density slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/625-distribution-workspace-lower-panel-density-slice-1.md');
    $panel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDigitalDistributionPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace Lower-Panel Density — Slice 1')
        ->toContain('compact notification summary')
        ->toContain('Presentation-only notification-panel density')
        ->and($panel)->toContain('data-testid="cockpit-digital-distribution-panel"')
        ->and($panel)->toContain('data-testid="cockpit-distribution-action-row"')
        ->and($frontendTest)->toContain("expect(panel.classes()).not.toContain('p-5')")
        ->and($frontendTest)->toContain("expect(actions[0].classes()).toContain('py-1.5')")
        ->and($compass)->toContain('Distribution Workspace Lower-Panel Density — Slice 1')
        ->and($settlementCompass)->toContain('Distribution Workspace Lower-Panel Density — Slice 1');
});

it('documents distribution workspace lower panel density slice 2', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/626-distribution-workspace-lower-panel-density-slice-2.md');
    $printPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPrintTemplatePanel.vue');
    $analyticsPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDistributionAnalyticsPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace Lower-Panel Density — Slice 2')
        ->toContain('compact print and evidence summaries')
        ->toContain('Presentation-only supporting-evidence density')
        ->and($printPanel)->toContain('data-testid="cockpit-print-template-panel"')
        ->and($analyticsPanel)->toContain('data-testid="cockpit-distribution-analytics-panel"')
        ->and($frontendTest)->toContain("expect(templates[0].classes()).toContain('p-3')")
        ->and($frontendTest)->toContain("expect(metrics[0].classes()).toContain('p-3')")
        ->and($compass)->toContain('Distribution Workspace Lower-Panel Density — Slice 2')
        ->and($settlementCompass)->toContain('Distribution Workspace Lower-Panel Density — Slice 2');
});

it('documents distribution workspace lower panel density slice 3 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/627-distribution-workspace-lower-panel-density-slice-3-closure.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $sharePanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitShareQrPanel.vue');
    $hostPage = file_get_contents($hostRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $hostSharePanel = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitShareQrPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace Lower-Panel Density — Slice 3 / Closure')
        ->toContain('Published package-owned Cockpit assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($page)->toContain('data-testid="cockpit-distribution-supporting-readiness-grid"')
        ->and($sharePanel)->toContain('data-testid="cockpit-share-qr-panel"')
        ->and($hostPage)->toContain($page)
        ->and($hostSharePanel)->toContain($sharePanel)
        ->and($frontendTest)->toContain("expect(supportingGrid.classes()).not.toContain('gap-6')")
        ->and($compass)->toContain('Distribution Workspace Lower-Panel Density — Slice 3 Closure')
        ->and($settlementCompass)->toContain('Distribution Workspace Lower-Panel Density — Slice 3');
});
