<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7i projection queue seam implementation baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/129-wave-7i-projection-queue-seam-implementation-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7I — Projection / Queue Seam Implementation Baseline')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Projection queues must be idempotent, observable, retry-safe, and non-authoritative for issuance truth.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('7J — Production Hardening Controls Closure')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7I — Projection / Queue Seam Implementation Baseline')
        ->and($cockpitCompass)->toContain('reports/129-wave-7i-projection-queue-seam-implementation-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7I — Projection / Queue Seam Implementation Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/129-wave-7i-projection-queue-seam-implementation-baseline.md');
});
