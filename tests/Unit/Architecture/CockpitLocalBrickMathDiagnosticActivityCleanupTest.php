<?php

declare(strict_types=1);

it('documents local brickmath diagnostic activity cleanup and expected ui effect', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/109-local-brickmath-diagnostic-activity-cleanup.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5M — Local BrickMath Diagnostic Activity Cleanup')
        ->and($report)->toContain('Status: Completed locally')
        ->and($report)->toContain('Remove YEZA / corr-cockpit-brickmath-5f')
        ->and($report)->toContain('yeza_count: 0')
        ->and($report)->toContain('mcpc_count: 1')
        ->and($report)->toContain('should no longer show `Pay Code YEZA issued`')
        ->and($report)->toContain('should continue showing the real `Pay Code MCPC issued`')
        ->and($report)->toContain('Manual UI Review — Cockpit Operator Issuance Activity')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5M — Local BrickMath Diagnostic Activity Cleanup')
        ->and($cockpitCompass)->toContain('reports/109-local-brickmath-diagnostic-activity-cleanup.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 5M — Local BrickMath Diagnostic Activity Cleanup')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/109-local-brickmath-diagnostic-activity-cleanup.md');
});
