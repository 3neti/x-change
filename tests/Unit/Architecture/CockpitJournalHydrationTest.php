<?php

declare(strict_types=1);

it('documents the journal cockpit hydration slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/024-journal-cockpit-hydration.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Integration Slice 2A — Journal Cockpit Hydration')
        ->and($report)->toContain('Hydrate read-only x-journal evidence summaries')
        ->and($report)->toContain('does not:')
        ->and($report)->toContain('write journal entries')
        ->and($report)->toContain('expose raw journal payloads')
        ->and($report)->toContain('move money')
        ->and($cockpitCompass)->toContain('Completed Host Integration Slice 2A — Journal Cockpit Hydration')
        ->and($cockpitCompass)->toContain('reports/024-journal-cockpit-hydration.md')
        ->and($settlementCompass)->toContain('x-change Host Integration Slice 2A — Journal Cockpit Hydration')
        ->and($settlementCompass)->toContain('No journal writes, raw payload exposure, provider calls, action execution, feedback delivery, voucher mutation, wallet access, or money movement were added');
});
