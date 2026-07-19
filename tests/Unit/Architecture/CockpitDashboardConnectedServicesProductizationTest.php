<?php

it('documents the dashboard connected services productization slice', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/547-dashboard-connected-services-productization-slice-1.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Dashboard Connected Services Productization Slice 1')
        ->toContain('Audit Trail / x-journal')
        ->toContain('Follow-Up Actions / x-action')
        ->toContain('Notifications / x-feedback')
        ->toContain('Balances / Treasury posture')
        ->toContain('Execution Evidence')
        ->toContain('no journal writes, action execution, feedback sends, campaign mutation, provider calls, wallet behavior changes, Treasury behavior changes, persistence changes, public API changes, or money movement were added')
        ->and($cockpitCompass)->toContain('Dashboard Connected Services Productization')
        ->and($cockpitCompass)->toContain('reports/547-dashboard-connected-services-productization-slice-1.md')
        ->and($settlementCompass)->toContain('Cockpit Dashboard Connected Services Productization')
        ->and($settlementCompass)->toContain('Dashboard connected-service overview productization in progress');
});
