<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6i high-volume projection   queue decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/119-wave-6i-high-volume-projection-queue-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6I — High-Volume Projection / Queue Decision')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Decide that high-volume durable activity projection needs explicit sync-versus-queue policy before production default enablement.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('6J — Production Readiness Closure')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6I — High-Volume Projection / Queue Decision')
        ->and($cockpitCompass)->toContain('reports/119-wave-6i-high-volume-projection-queue-decision.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6I — High-Volume Projection / Queue Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/119-wave-6i-high-volume-projection-queue-decision.md');
});
