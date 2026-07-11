<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6j production readiness closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/120-wave-6j-production-readiness-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6J — Production Readiness Closure')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Close Wave 6 production hardening plan and keep production default enablement deferred pending implementation of hardening controls.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('Manual UI Review / Wave 6 follow-up planning')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6J — Production Readiness Closure')
        ->and($cockpitCompass)->toContain('reports/120-wave-6j-production-readiness-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6J — Production Readiness Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/120-wave-6j-production-readiness-closure.md');
});
