<?php

declare(strict_types=1);

it('documents pay code explorer row action width polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/594-pay-code-explorer-row-action-width-polish-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Row Action Width Polish — Slice 1')
        ->toContain('fixed scan width')
        ->toContain('Presentation-only row action width polish')
        ->and($component)->toContain('flex justify-end gap-1.5')
        ->and($component)->toContain('h-8 w-8 items-center justify-center')
        ->and($component)->toContain('aria-label')
        ->and($component)->toContain('min-h-9 items-center justify-center')
        ->and($frontendTest)->toContain('keeps row action controls stable-width')
        ->and($frontendTest)->toContain('justify-end')
        ->and($frontendTest)->toContain('min-h-9');
});

it('documents pay code explorer row action width polish slice 2 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/595-pay-code-explorer-row-action-width-polish-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostComponent = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Row Action Width Polish — Slice 2 / Closure')
        ->toContain('Published Cockpit package assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($component)->toContain('flex justify-end gap-1.5')
        ->and($hostComponent)->toContain('flex justify-end gap-1.5')
        ->and($hostComponent)->toContain('h-8 w-8 items-center justify-center')
        ->and($hostComponent)->toContain('aria-label')
        ->and($hostComponent)->toContain('min-h-9 items-center justify-center')
        ->and($compass)->toContain('Completed Pay Code Explorer Row Action Width Polish Slice 2 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Row Action Width Polish — Slice 2');
});
