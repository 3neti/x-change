<?php

declare(strict_types=1);

it('documents quick generate primary workflow compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/634-quick-generate-primary-workflow-compression-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/QuickGenerate.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Primary Workflow Compression — Slice 1')
        ->toContain('slim operational header')
        ->toContain('Presentation-only shell compression')
        ->and($page)->toContain('data-testid="cockpit-quick-generate-header"')
        ->and($page)->toContain('data-testid="cockpit-quick-generate-header-progress"')
        ->and($page)->toContain('Create a Pay Code')
        ->and($page)->not->toContain('data-testid="cockpit-quick-generate-header-facts"')
        ->and($frontendTest)->toContain("expect(header.classes()).toContain('py-3')")
        ->and($frontendTest)->toContain("expect(headerProgress.text()).toContain('Review')")
        ->and($compass)->toContain('Quick Generate Primary Workflow Compression — Slice 1')
        ->and($settlementCompass)->toContain('Quick Generate Primary Workflow Compression — Slice 1');
});

it('documents quick generate primary workflow compression slice 2', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/635-quick-generate-primary-workflow-compression-slice-2.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/QuickGenerate.vue');
    $disclosure = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDiagnosticsDisclosure.vue');
    $handoff = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitGenerateActionPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Primary Workflow Compression — Slice 2')
        ->toContain('closed handoff-status disclosure')
        ->toContain('Presentation-only secondary-control compression')
        ->and($page)->toContain('data-testid="cockpit-quick-generate-reference-guide"')
        ->and($page)->toContain('compact')
        ->and($disclosure)->toContain('compact?: boolean')
        ->and($handoff)->toContain('4 safeguards')
        ->and($frontendTest)->toContain("expect(panel.attributes('open')).toBeUndefined()")
        ->and($frontendTest)->toContain("expect(disclosure.classes()).toContain('py-3')")
        ->and($compass)->toContain('Quick Generate Primary Workflow Compression — Slice 2')
        ->and($settlementCompass)->toContain('Quick Generate Primary Workflow Compression — Slice 2');
});

it('documents quick generate primary workflow compression slice 3 closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/636-quick-generate-primary-workflow-compression-slice-3-closure.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/QuickGenerate.vue');
    $summary = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateDiagnosticsSummary.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts');
    $hostPage = file_get_contents(dirname($packageRoot, 2).'/resources/js/cockpit/pages/QuickGenerate.vue');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Primary Workflow Compression — Slice 3 / Closure')
        ->toContain('compact eight-check readiness grid')
        ->toContain('Presentation-only primary-workflow compression closure')
        ->and($page)->toContain('data-testid="cockpit-quick-generate-primary-workflow-stack"')
        ->and($page)->not->toContain('data-testid="cockpit-quick-generate-engineering-diagnostics"')
        ->and($page)->not->toContain('data-testid="cockpit-quick-generate-full-history"')
        ->and($frontendTest)->toContain("expect(wrapper.text()).not.toContain('Engineering diagnostics')")
        ->and($summary)->toContain('{{ items.length }} checks')
        ->and($summary)->toContain('data-testid="cockpit-quick-generate-diagnostics-summary-grid"')
        ->and($frontendTest)->toContain("expect(workflowStack.classes()).toContain('space-y-3')")
        ->and($hostPage)->toContain('Create a Pay Code')
        ->and($compass)->toContain('Quick Generate Primary Workflow Compression — Slice 3 Closure')
        ->and($settlementCompass)->toContain('Quick Generate Primary Workflow Compression — Slice 3');
});

it('keeps quick generate instruction copy task oriented', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $panel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');

    expect($panel)
        ->toContain('Instructions And Safeguards')
        ->toContain('Issuance Details')
        ->toContain('Claim Requirements')
        ->toContain('Validation And Verification')
        ->toContain('Claim Experience')
        ->toContain('Status Updates')
        ->toContain('Claim Schedule And Availability')
        ->toContain('Advanced Settlement Settings')
        ->not->toContain('CreateV2-inspired primary controls')
        ->not->toContain('Advanced DTO fields remain opt-in')
        ->not->toContain('Basic claim checks map to cash validation')
        ->not->toContain('Open-slice settings are passed through existing instruction metadata');
});
