<?php

declare(strict_types=1);

it('documents real activity production readiness decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/107-real-activity-production-readiness-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Wave 5K — Real Activity Production Readiness Decision')
        ->and($report)->toContain('Status: Not ready for production default enablement')
        ->and($report)->toContain('Do not enable durable Cockpit operator issuance activity recording by default in production yet.')
        ->and($report)->toContain('Operator authorization and tenant scoping')
        ->and($report)->toContain('Retention and purge schedule')
        ->and($report)->toContain('Production observability for recorder failures')
        ->and($report)->toContain('Durable operator issuance activity remains package-supported but disabled by default.')
        ->and($report)->toContain('Expected UI effect:')
        ->and($report)->toContain('None.')
        ->and($report)->toContain('Wave 5L — Cockpit Mutation Wave 5 Closure Report')
        ->and($cockpitCompass)->toContain('Wave 5K — Real Activity Production Readiness Decision')
        ->and($cockpitCompass)->toContain('reports/107-real-activity-production-readiness-decision.md')
        ->and($settlementCompass)->toContain('Wave 5K — Real Activity Production Readiness Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/107-real-activity-production-readiness-decision.md');
});
