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

it('documents pay code explorer secondary controls compression slice 2', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/614-pay-code-explorer-secondary-controls-compression-slice-2.md');
    $filterBuilder = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeFilterBuilder.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerFoundation.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Secondary Controls Compression — Slice 2')
        ->toContain('compact Filter Details disclosure')
        ->toContain('Presentation-only filter detail compression')
        ->and($filterBuilder)->toContain('Read-only query criteria.')
        ->and($filterBuilder)->toContain('rounded-full bg-slate-50 p-1.5')
        ->and($filterBuilder)->toContain('px-4 py-3')
        ->and($filterBuilder)->toContain('Filtering uses normal GET navigation and only changes what the operator sees.')
        ->and($frontendTest)->toContain('rounded-full')
        ->and($frontendTest)->toContain('py-3');
});

it('documents pay code explorer secondary controls compression slice 3 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/615-pay-code-explorer-secondary-controls-compression-slice-3-closure.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $hostPage = file_get_contents($hostRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $filterBuilder = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeFilterBuilder.vue');
    $hostFilterBuilder = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitPayCodeFilterBuilder.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Secondary Controls Compression — Slice 3 / Closure')
        ->toContain('Published package-owned Cockpit assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($page)->toContain('Read-only rules, totals, and connected-service context.')
        ->and($hostPage)->toContain('Read-only rules, totals, and connected-service context.')
        ->and($filterBuilder)->toContain('Read-only query criteria.')
        ->and($hostFilterBuilder)->toContain('Read-only query criteria.')
        ->and($hostFilterBuilder)->toContain('rounded-full bg-slate-50 p-1.5')
        ->and($compass)->toContain('Completed Pay Code Explorer Secondary Controls Compression Slice 3 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Secondary Controls Compression — Slice 3');
});
