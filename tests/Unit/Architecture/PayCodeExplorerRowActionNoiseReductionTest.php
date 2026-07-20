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
