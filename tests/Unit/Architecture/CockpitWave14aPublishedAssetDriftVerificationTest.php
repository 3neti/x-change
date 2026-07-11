<?php

declare(strict_types=1);

it('documents cockpit wave 14a published asset drift verification', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/175-wave-14a-published-asset-drift-verification.md');

    expect($report)->toContain('php artisan x-change:doctor --assets --json')
        ->and($report)->toContain('checked: 56')
        ->and($report)->toContain('stale: 0')
        ->and($report)->toContain('missing: 0')
        ->and($report)->toContain('extra: 0')
        ->and($report)->toContain('published Cockpit assets match package source');
});
