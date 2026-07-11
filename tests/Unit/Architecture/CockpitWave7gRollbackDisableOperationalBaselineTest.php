<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7g rollback disable operational baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/127-wave-7g-rollback-disable-operational-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7G — Rollback / Disable Operational Baseline')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Operators must be able to disable durable activity recording and handoffs without breaking Quick Generate issuance.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('7H — Activity Search / Filter Implementation Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7G — Rollback / Disable Operational Baseline')
        ->and($cockpitCompass)->toContain('reports/127-wave-7g-rollback-disable-operational-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7G — Rollback / Disable Operational Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/127-wave-7g-rollback-disable-operational-baseline.md');
});
