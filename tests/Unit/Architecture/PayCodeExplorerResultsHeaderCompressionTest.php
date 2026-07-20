<?php

declare(strict_types=1);

it('documents pay code explorer results header compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/604-pay-code-explorer-results-header-compression-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Results Header Compression — Slice 1')
        ->toContain('Converted the results density summary into a tighter pill-style metric strip')
        ->toContain('Presentation-only results header compression')
        ->and($component)->toContain('sm:w-[30rem]')
        ->and($component)->toContain('rounded-full bg-slate-50 p-1.5')
        ->and($component)->toContain('data-testid="cockpit-pay-code-result-limit-notice"')
        ->and($component)->toContain('data-testid="cockpit-pay-code-result-pagination"')
        ->and($frontendTest)->toContain('renders the results header as a compact pagination toolbar');
});
