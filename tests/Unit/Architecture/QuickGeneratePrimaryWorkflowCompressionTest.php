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
        ->and($page)->toContain('data-testid="cockpit-quick-generate-header-facts"')
        ->and($page)->toContain('Workflow limits')
        ->and($frontendTest)->toContain("expect(header.classes()).toContain('py-3')")
        ->and($frontendTest)->toContain("expect(headerBoundary.attributes('open')).toBeUndefined()")
        ->and($compass)->toContain('Quick Generate Primary Workflow Compression — Slice 1')
        ->and($settlementCompass)->toContain('Quick Generate Primary Workflow Compression — Slice 1');
});
