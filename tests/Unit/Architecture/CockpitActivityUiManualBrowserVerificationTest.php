<?php

declare(strict_types=1);

it('documents the cockpit activity ui manual browser verification checkpoint', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/061-activity-ui-manual-browser-verification.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2K — Activity UI Manual Browser Verification')
        ->and($report)->toContain('Status: Blocked for browser visual confirmation')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('browser runtime unavailable before page navigation')
        ->and($report)->toContain('No visual pass is claimed')
        ->and($report)->toContain('php artisan route:list --path=x/cockpit')
        ->and($report)->toContain('php artisan x-change:doctor --assets --json')
        ->and($report)->toContain('npm run build')
        ->and($report)->toContain('Published Cockpit assets: passed')
        ->and($report)->toContain('checked: 55')
        ->and($report)->toContain('ok: 55')
        ->and($report)->toContain('stale: 0')
        ->and($report)->toContain('missing: 0')
        ->and($report)->toContain('extra: 0')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('money movement')
        ->and($report)->toContain('Cockpit Mutation Wave 2L — Human Activity UI Visual Confirmation Record')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2K — Activity UI Manual Browser Verification')
        ->and($cockpitCompass)->toContain('reports/061-activity-ui-manual-browser-verification.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2K — Activity UI Manual Browser Verification')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/061-activity-ui-manual-browser-verification.md');
});
