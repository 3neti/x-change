<?php

declare(strict_types=1);

it('documents the action cockpit hydration slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/025-action-cockpit-hydration.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Integration Slice 2B — Action Cockpit Hydration')
        ->and($report)->toContain('Hydrate x-action read-model CTA summaries')
        ->and($report)->toContain('does not:')
        ->and($report)->toContain('execute actions')
        ->and($report)->toContain('expose raw diagnostics')
        ->and($report)->toContain('move money')
        ->and($cockpitCompass)->toContain('Completed Host Integration Slice 2B — Action Cockpit Hydration')
        ->and($cockpitCompass)->toContain('reports/025-action-cockpit-hydration.md')
        ->and($settlementCompass)->toContain('x-change Host Integration Slice 2B — Action Cockpit Hydration')
        ->and($settlementCompass)->toContain('No action execution, workflow authorization, raw diagnostics exposure, target URL exposure, journal writes, feedback delivery, provider calls, voucher mutation, wallet access, or money movement were added');
});
