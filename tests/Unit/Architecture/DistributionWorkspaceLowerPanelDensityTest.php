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
