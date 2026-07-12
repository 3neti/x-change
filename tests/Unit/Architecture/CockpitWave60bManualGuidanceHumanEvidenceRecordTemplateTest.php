<?php

declare(strict_types=1);

it('documents cockpit wave 60b manual guidance human evidence record template', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/358-wave-60b-manual-guidance-human-evidence-record-template.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 60B — Manual Guidance Human Evidence Record Template')
        ->and($report)->toContain('Reviewer:')
        ->and($report)->toContain('Review date:')
        ->and($report)->toContain('Environment:')
        ->and($report)->toContain('Browser:')
        ->and($report)->toContain('Pay Code:')
        ->and($report)->toContain('Voucher Detail Evidence')
        ->and($report)->toContain('Distribution Workspace Evidence')
        ->and($report)->toContain('Guidance states manual distribution only: yes / no')
        ->and($report)->toContain('Guidance states approved external workflow: yes / no')
        ->and($report)->toContain('Guidance states verify recipient: yes / no')
        ->and($report)->toContain('Guidance states no Cockpit delivery: yes / no')
        ->and($report)->toContain('Guidance states no copy telemetry: yes / no')
        ->and($report)->toContain('Guidance states no short links or QR assets: yes / no')
        ->and($report)->toContain('Guidance states sensitive settlement access material: yes / no')
        ->and($report)->toContain('Final decision: Pass / Blocked / Fail')
        ->and($report)->toContain('pending-human-guidance-intake')
        ->and($report)->toContain('Cockpit Wave 60C — Manual Guidance Acceptance Decision Policy')
        ->and($cockpitCompass)->toContain('Cockpit Wave 60B — Manual Guidance Human Evidence Record Template')
        ->and($cockpitCompass)->toContain('reports/358-wave-60b-manual-guidance-human-evidence-record-template.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 60B — Manual Guidance Human Evidence Record Template')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/358-wave-60b-manual-guidance-human-evidence-record-template.md');
});
