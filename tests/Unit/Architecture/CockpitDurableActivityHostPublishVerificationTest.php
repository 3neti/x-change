<?php

declare(strict_types=1);

it('documents the cockpit durable activity host publish verification checkpoint', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/075-durable-activity-host-publish-manual-verification.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3M — Durable Activity Host Publish / Manual Verification')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('checked: 55')
        ->and($report)->toContain('stale: 0')
        ->and($report)->toContain('missing: 0')
        ->and($report)->toContain('No `php artisan x-change:install --force` was run')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('Cockpit Mutation Wave 3N — Durable Activity Closure / Production Readiness Decision')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3M — Durable Activity Host Publish / Manual Verification')
        ->and($cockpitCompass)->toContain('reports/075-durable-activity-host-publish-manual-verification.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3M — Durable Activity Host Publish / Manual Verification')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/075-durable-activity-host-publish-manual-verification.md');
});
