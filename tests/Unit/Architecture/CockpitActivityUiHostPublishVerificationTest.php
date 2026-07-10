<?php

declare(strict_types=1);

it('documents the cockpit activity ui host publish verification checkpoint', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/060-activity-ui-host-publish-verification.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2J — Activity UI Host Publish Verification')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('php artisan x-change:install --force')
        ->and($report)->toContain('php artisan x-change:doctor --assets --json')
        ->and($report)->toContain('Published Cockpit assets: passed')
        ->and($report)->toContain('checked: 55')
        ->and($report)->toContain('ok: 55')
        ->and($report)->toContain('stale: 0')
        ->and($report)->toContain('missing: 0')
        ->and($report)->toContain('extra: 0')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityPanel.vue')
        ->and($report)->toContain('no manual host mirror edits')
        ->and($report)->toContain('no journal writes')
        ->and($report)->toContain('no action execution')
        ->and($report)->toContain('no feedback delivery')
        ->and($report)->toContain('no money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2J — Activity UI Host Publish Verification')
        ->and($cockpitCompass)->toContain('reports/060-activity-ui-host-publish-verification.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2J — Activity UI Host Publish Verification')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/060-activity-ui-host-publish-verification.md');
});
