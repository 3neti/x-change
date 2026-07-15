<?php

declare(strict_types=1);

it('documents the cockpit durable activity runtime opt-in configuration', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/072-durable-activity-runtime-opt-in-configuration.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $config = file_get_contents($packageRoot.'/config/x-change.php');
    $provider = file_get_contents($packageRoot.'/src/Providers/XChangeServiceProvider.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3J — Durable Activity Runtime Opt-In Configuration')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('x-change.cockpit.operator_issuance_activity.repository')
        ->and($report)->toContain('x-change.cockpit.operator_issuance_activity.recorder')
        ->and($report)->toContain('DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('DatabaseCockpitOperatorIssuanceActivityRecorder')
        ->and($report)->toContain('No Cockpit UI was changed')
        ->and($report)->toContain('Cockpit Mutation Wave 3K — Durable Activity Read Model Adapter')
        ->and($config)->toContain("'operator_issuance_activity' => [")
        ->and($config)->toContain("'available_repositories' => [")
        ->and($config)->toContain("'available_recorders' => [")
        ->and($provider)->toContain('cockpitOperatorIssuanceActivityService')
        ->and($provider)->toContain("'recorder'")
        ->and($provider)->toContain("'repository'")
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3J — Durable Activity Runtime Opt-In Configuration')
        ->and($cockpitCompass)->toContain('reports/072-durable-activity-runtime-opt-in-configuration.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3J — Durable Activity Runtime Opt-In Configuration')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/072-durable-activity-runtime-opt-in-configuration.md');
});
