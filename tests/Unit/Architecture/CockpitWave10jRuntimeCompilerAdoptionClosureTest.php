<?php

declare(strict_types=1);

it('documents cockpit wave 10j runtime compiler adoption closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/160-wave-10j-runtime-compiler-adoption-closure.md';
    $cockpitCompassPath = $packageRoot.'/docs/ui-cockpit/COMPASS.md';
    $settlementCompassPath = $packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md';

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($cockpitCompassPath);
    $settlementCompass = file_get_contents($settlementCompassPath);

    expect($report)->toContain('Wave 10A')
        ->and($report)->toContain('Wave 10J')
        ->and($report)->toContain('Runtime compiler adoption complete')
        ->and($report)->toContain('GeneratePayCode')
        ->and($report)->toContain('preflight.pricing')
        ->and($report)->toContain('preflight.funding')
        ->and($report)->toContain('metadata.campaign')
        ->and($report)->toContain('No public Pay Code API route replacement')
        ->and($cockpitCompass)->toContain('reports/160-wave-10j-runtime-compiler-adoption-closure.md')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/160-wave-10j-runtime-compiler-adoption-closure.md');
});
