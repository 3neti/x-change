<?php

declare(strict_types=1);

it('documents pay code explorer top control strip simplification slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/598-pay-code-explorer-top-control-strip-simplification-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Top Control Strip Simplification — Slice 1')
        ->toContain('single `Page details` disclosure')
        ->toContain('Presentation-only top control strip simplification')
        ->and($page)->toContain('data-testid="cockpit-pay-code-page-details-disclosure"')
        ->and($page)->toContain('Page details')
        ->and($page)->toContain('Open this panel for row-action rules, list totals, and connected-service readiness.')
        ->and($frontendTest)->toContain('groups secondary utility panels behind one page details disclosure')
        ->and($frontendTest)->toContain('cockpit-pay-code-page-details-disclosure');
});

it('documents pay code explorer top control strip simplification slice 2 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/599-pay-code-explorer-top-control-strip-simplification-slice-2-closure.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $hostPage = file_get_contents($hostRoot.'/resources/js/cockpit/pages/PayCodeExplorer.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Top Control Strip Simplification — Slice 2 / Closure')
        ->toContain('Published Cockpit package assets')
        ->toContain('Closed / pending human browser inspection')
        ->and($page)->toContain('data-testid="cockpit-pay-code-page-details-disclosure"')
        ->and($hostPage)->toContain('data-testid="cockpit-pay-code-page-details-disclosure"')
        ->and($hostPage)->toContain('Page details')
        ->and($hostPage)->toContain('cockpit-pay-code-row-action-guidance')
        ->and($hostPage)->toContain('cockpit-pay-code-integration-readiness')
        ->and($compass)->toContain('Completed Pay Code Explorer Top Control Strip Simplification Slice 2 / Closure')
        ->and($settlementCompass)->toContain('Pay Code Explorer Top Control Strip Simplification — Slice 2');
});
