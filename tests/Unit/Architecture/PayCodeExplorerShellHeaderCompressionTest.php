<?php

declare(strict_types=1);

it('documents pay code explorer shell header compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/606-pay-code-explorer-shell-header-compression-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Shell Header Compression — Slice 1')
        ->toContain('Converted the read-model, records, and payload-policy facts into compact pill facts')
        ->toContain('Presentation-only shell header compression')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-shell-header"')
        ->and($page)->toContain('data-testid="cockpit-pay-code-explorer-shell-facts"')
        ->and($page)->toContain('xl:w-[32rem]')
        ->and($frontendTest)->toContain('renders the explorer shell header as a compact page intro');
});
