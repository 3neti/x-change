<?php

declare(strict_types=1);

it('documents cockpit wave 11c published asset drift handoff', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/163-wave-11c-published-asset-drift-verification-host-publish-handoff.md');

    expect($report)->toContain('CockpitQuickGenerateSubmitPanel.vue')
        ->and($report)->toContain('types.ts')
        ->and($report)->toContain('php artisan x-change:install --force')
        ->and($report)->toContain('stale: 2')
        ->and($report)->toContain('missing: 0')
        ->and($report)->toContain('extra: 0');
});
