<?php

declare(strict_types=1);

it('documents pay code explorer amount scan polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/592-pay-code-explorer-amount-scan-polish-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Amount Scan Polish — Slice 1')
        ->toContain('Right-aligned the desktop `Amount` column')
        ->toContain('Presentation-only amount scan polish')
        ->and($component)->toContain('cockpit-pay-code-amount')
        ->and($component)->toContain('cockpit-pay-code-mobile-amount')
        ->and($component)->toContain('text-right font-mono tabular-nums')
        ->and($frontendTest)->toContain('renders scan-friendly amount values')
        ->and($frontendTest)->toContain('cockpit-pay-code-mobile-amount');
});
