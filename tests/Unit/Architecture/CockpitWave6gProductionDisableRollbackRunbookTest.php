<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6g production disable   rollback runbook', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/117-wave-6g-production-disable-rollback-runbook.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6G — Production Disable / Rollback Runbook')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Define the runbook for disabling durable activity recording safely in production.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('6H — Cockpit Activity Search / Filter Plan')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6G — Production Disable / Rollback Runbook')
        ->and($cockpitCompass)->toContain('reports/117-wave-6g-production-disable-rollback-runbook.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6G — Production Disable / Rollback Runbook')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/117-wave-6g-production-disable-rollback-runbook.md');
});
