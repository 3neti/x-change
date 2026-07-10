<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff operator diagnostics host publish verification', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/087-durable-activity-journal-handoff-operator-diagnostics-host-publish-verification.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4K — Durable Activity Journal Handoff Operator Diagnostics Host Publish / Verification')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('checked 55')
        ->and($report)->toContain('stale 0')
        ->and($report)->toContain('missing 0')
        ->and($report)->toContain('npm run build')
        ->and($report)->toContain('74 passed')
        ->and($report)->toContain('476 tests')
        ->and($report)->toContain('Did not run `php artisan x-change:install --force`')
        ->and($report)->toContain('Cockpit Mutation Wave 4L — Durable Activity Journal Handoff Operator Diagnostics Manual Browser Verification')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4K — Durable Activity Journal Handoff Operator Diagnostics Host Publish / Verification')
        ->and($cockpitCompass)->toContain('reports/087-durable-activity-journal-handoff-operator-diagnostics-host-publish-verification.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4K — Durable Activity Journal Handoff Operator Diagnostics Host Publish / Verification')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/087-durable-activity-journal-handoff-operator-diagnostics-host-publish-verification.md');
});
