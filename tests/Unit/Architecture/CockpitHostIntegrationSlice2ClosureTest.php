<?php

declare(strict_types=1);

it('documents closure of the read-only journal action feedback cockpit branch', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/032-host-integration-slice-2-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Integration Slice 2I — Read-Only Journal / Action / Feedback Cockpit Closure')
        ->and($report)->toContain('Slice 2A — Journal Cockpit Hydration')
        ->and($report)->toContain('Slice 2H — Authorization / Redaction Review')
        ->and($report)->toContain('This branch did not add:')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Completed Host Integration Slice 2I — Read-Only Journal / Action / Feedback Cockpit Closure')
        ->and($cockpitCompass)->toContain('reports/032-host-integration-slice-2-closure.md')
        ->and($settlementCompass)->toContain('x-change Host Integration Slice 2I — Read-Only Journal / Action / Feedback Cockpit Closure')
        ->and($settlementCompass)->toContain('The read-only Journal / Action / Feedback Cockpit hydration branch is closed through Slice 2I');
});
