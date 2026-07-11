<?php

declare(strict_types=1);

it('documents cockpit wave 15c browser evidence log snapshot', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/182-wave-15c-browser-evidence-log-snapshot.md');

    expect($report)->toContain('Browser Evidence / Log Snapshot')
        ->and($report)->toContain('browser_logs')
        ->and($report)->toContain('Vite reconnect')
        ->and($report)->toContain('No new Wave 15 blocking browser exception')
        ->and($report)->toContain('/x/cockpit/quick-generate')
        ->and($report)->toContain('/x/balances');
});
