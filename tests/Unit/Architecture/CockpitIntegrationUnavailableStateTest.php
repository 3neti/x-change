<?php

declare(strict_types=1);

it('documents the cockpit integration unavailable state hardening slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/030-integration-unavailable-state-hardening.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Integration Slice 2G — Integration Error / Unavailable States')
        ->and($report)->toContain('display safe unavailable reasons')
        ->and($report)->toContain('Exception classes and exception messages remain hidden')
        ->and($report)->toContain('does not:')
        ->and($report)->toContain('retry failed adapters')
        ->and($report)->toContain('move money')
        ->and($cockpitCompass)->toContain('Completed Host Integration Slice 2G — Integration Error / Unavailable States')
        ->and($cockpitCompass)->toContain('reports/030-integration-unavailable-state-hardening.md')
        ->and($settlementCompass)->toContain('x-change Host Integration Slice 2G — Integration Error / Unavailable States')
        ->and($settlementCompass)->toContain('No exception messages, exception classes, adapter retries, queues, observability exporters, journal writes, action execution, feedback delivery, provider calls, voucher mutation, wallet access, or money movement were added');
});
