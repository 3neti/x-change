<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7j production hardening controls closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/130-wave-7j-production-hardening-controls-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7J — Production Hardening Controls Closure')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Durable activity production enablement remains blocked until runtime enforcement slices implement the Wave 7 controls.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('Manual UI Review / Wave 8 runtime enforcement planning')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7J — Production Hardening Controls Closure')
        ->and($cockpitCompass)->toContain('reports/130-wave-7j-production-hardening-controls-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7J — Production Hardening Controls Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/130-wave-7j-production-hardening-controls-closure.md');
});
