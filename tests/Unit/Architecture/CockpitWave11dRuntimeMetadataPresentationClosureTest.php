<?php

declare(strict_types=1);

it('documents cockpit wave 11d runtime metadata presentation closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/164-wave-11d-runtime-metadata-presentation-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Wave 11A')
        ->and($report)->toContain('Wave 11D')
        ->and($report)->toContain('pricing preflight')
        ->and($report)->toContain('funding preflight')
        ->and($report)->toContain('draft runtime')
        ->and($report)->toContain('activity runtime')
        ->and($report)->toContain('No new mutation controls')
        ->and($report)->toContain('php artisan x-change:install --force')
        ->and($cockpitCompass)->toContain('reports/164-wave-11d-runtime-metadata-presentation-closure.md')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/164-wave-11d-runtime-metadata-presentation-closure.md');
});
