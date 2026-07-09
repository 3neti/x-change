<?php

declare(strict_types=1);

it('documents the cockpit browser log preflight record before human visual confirmation', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/036-browser-log-preflight-record.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Validation Checkpoint 4 — Browser Log Preflight Record')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('/x/cockpit')
        ->and($report)->toContain('server connection lost. Polling for restart')
        ->and($report)->toContain('/x/balances')
        ->and($report)->toContain('outside the Cockpit route')
        ->and($report)->toContain('Human visual confirmation is still required')
        ->and($report)->toContain('This checkpoint did not add:')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Host Validation Checkpoint 4 — Browser Log Preflight Record')
        ->and($cockpitCompass)->toContain('Browser-log preflight recorded; human visual browser confirmation still pending')
        ->and($cockpitCompass)->toContain('reports/036-browser-log-preflight-record.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 4 — Browser Log Preflight Record')
        ->and($settlementCompass)->toContain('Recent browser logs include `/x/cockpit` Vite debug entries');
});
