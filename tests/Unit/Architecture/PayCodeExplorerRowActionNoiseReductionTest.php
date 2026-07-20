<?php

declare(strict_types=1);

it('documents pay code explorer row action noise reduction slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/596-pay-code-explorer-row-action-noise-reduction-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Row Action Noise Reduction — Slice 1')
        ->toContain('quieter `More` disclosure')
        ->toContain('Presentation-only row action noise reduction')
        ->and($component)->toContain('data-testid="cockpit-pay-code-row-unavailable-actions"')
        ->and($component)->toContain('data-testid="cockpit-pay-code-mobile-row-unavailable-actions"')
        ->and($component)->toContain('More')
        ->and($component)->toContain('sr-only')
        ->and($frontendTest)->toContain('keeps unavailable row action counts behind a quiet disclosure')
        ->and($frontendTest)->toContain('cockpit-pay-code-mobile-row-action-disabled');
});

it('documents pay code explorer row action noise reduction slice 2 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/597-pay-code-explorer-row-action-noise-reduction-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostComponent = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Row Action Noise Reduction — Slice 2 / Closure')
        ->toContain('Published Cockpit package assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($component)->toContain('data-testid="cockpit-pay-code-row-unavailable-actions"')
        ->and($hostComponent)->toContain('data-testid="cockpit-pay-code-row-unavailable-actions"')
        ->and($hostComponent)->toContain('data-testid="cockpit-pay-code-mobile-row-unavailable-actions"')
        ->and($hostComponent)->toContain('More')
        ->and($hostComponent)->toContain('sr-only')
        ->and($compass)->toContain('Completed Pay Code Explorer Row Action Noise Reduction Slice 2 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Row Action Noise Reduction — Slice 2');
});
