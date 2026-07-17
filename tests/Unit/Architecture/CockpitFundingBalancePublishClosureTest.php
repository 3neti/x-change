<?php

declare(strict_types=1);

it('documents cockpit funding balance publish closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/458-funding-balance-ui-wave-slice-3-publish-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Funding / Balance UI Wave — Slice 3 — Host Publish / Closure')
        ->and($report)->toContain('checked 60, ok 60, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Focused frontend dashboard test')
        ->and($report)->toContain('Host production build: passed')
        ->and($report)->toContain('Bridge estimates')
        ->and($report)->toContain('Treasury facts deferred')
        ->and($report)->toContain('No wallet Treasury runtime dependency')
        ->and($cockpitCompass)->toContain('Cockpit Funding / Balance UI Wave — Slice 3 — Host Publish / Closure')
        ->and($cockpitCompass)->toContain('reports/458-funding-balance-ui-wave-slice-3-publish-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Funding / Balance UI Wave — Slice 3 — Host Publish / Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/458-funding-balance-ui-wave-slice-3-publish-closure.md');
});
