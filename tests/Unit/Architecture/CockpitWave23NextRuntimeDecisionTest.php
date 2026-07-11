<?php

declare(strict_types=1);

it('documents cockpit wave 23 next runtime decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/195-wave-23-next-runtime-decision-record.md');

    expect($report)->toContain('Decision recorded')
        ->and($report)->toContain('Cockpit Wave 24 — Operator Activity Search / Filter Runtime Readiness')
        ->and($report)->toContain('operators still need read-only discovery tools')
        ->and($report)->toContain('Search and filtering improve operational usefulness without changing execution')
        ->and($report)->toContain('search contract baselines')
        ->and($report)->toContain('filter contract baselines')
        ->and($report)->toContain('runtime configuration mutation UI')
        ->and($report)->toContain('enabling/disabling journal/action/feedback handoffs from Cockpit')
        ->and($report)->toContain('Search/filtering must not write journal entries')
        ->and($report)->toContain('Wave 23B has no UI impact')
        ->and($report)->toContain('Runtime mutation remains blocked')
        ->and($report)->toContain('Cockpit Wave 23C — Compass Closure');
});
