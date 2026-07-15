<?php

declare(strict_types=1);

it('documents execution integration wave closure and the next explicit target', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/architecture/execution-engine/reports/023-execution-integration-wave-closure-next-decision.md');
    $executionCompass = file_get_contents($packageRoot.'/docs/architecture/execution-engine/EXECUTION_ENGINE_COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');

    expect($report)
        ->toContain('Execution Integration Slice 16')
        ->toContain('Completed')
        ->toContain('voucher Execution Engine')
        ->toContain('x-journal execution result handoff')
        ->toContain('x-action continuation planning handoff')
        ->toContain('x-feedback delivery planning handoff')
        ->toContain('Cockpit UI surfacing')
        ->toContain('Playwright browser verification')
        ->toContain('The current execution integration wave is closed')
        ->toContain('No further execution integration work should proceed implicitly')
        ->toContain('Quick Generate Productization Wave')
        ->toContain('Quick Generate Productization Slice 1 — Result Panel and Diagnostic Demotion Plan');

    expect($executionCompass)
        ->toContain('Execution Integration Slice 16 — Closure / Next Integration Decision')
        ->toContain('reports/023-execution-integration-wave-closure-next-decision.md')
        ->toContain('Quick Generate Productization Wave');

    expect($settlementCompass)
        ->toContain('Execution Integration Slice 16 — Closure / Next Integration Decision')
        ->toContain('The current execution integration wave is closed');

    expect($cockpitCompass)
        ->toContain('Execution Integration Slice 16 — Closure / Next Integration Decision')
        ->toContain('Quick Generate productization');
});
