<?php

declare(strict_types=1);

it('documents pay code explorer secondary controls compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/613-pay-code-explorer-secondary-controls-compression-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Secondary Controls Compression — Slice 1')
        ->toContain('slim utility disclosure row')
        ->toContain('Presentation-only secondary control compression')
        ->and($page)->toContain('Read-only rules, totals, and connected-service context.')
        ->and($page)->toContain('class="rounded-xl border border-slate-200 bg-white px-4 py-3')
        ->and($page)->toContain('data-testid="cockpit-pay-code-page-details-disclosure"')
        ->and($page)->toContain('The main scan path stays focused on search and results.')
        ->and($page)->not->toContain('Open this panel for row-action rules, list totals, and connected-service readiness.')
        ->and($frontendTest)->toContain('Read-only rules, totals, and connected-service context.')
        ->and($frontendTest)->toContain('py-3');
});
