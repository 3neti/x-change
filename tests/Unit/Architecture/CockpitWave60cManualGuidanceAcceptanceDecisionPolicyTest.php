<?php

declare(strict_types=1);

it('documents cockpit wave 60c manual guidance acceptance decision policy', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/359-wave-60c-manual-guidance-acceptance-decision-policy.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 60C — Manual Guidance Acceptance Decision Policy')
        ->and($report)->toContain('Pass')
        ->and($report)->toContain('Blocked')
        ->and($report)->toContain('Fail')
        ->and($report)->toContain('Voucher Detail was inspected.')
        ->and($report)->toContain('Distribution Workspace was inspected.')
        ->and($report)->toContain('Both pages showed visible manual distribution guidance.')
        ->and($report)->toContain('No usable Pay Code is available.')
        ->and($report)->toContain('Host-published Cockpit assets are stale.')
        ->and($report)->toContain('Guidance is missing from either surface.')
        ->and($report)->toContain('Guidance implies Cockpit delivery by SMS, email, webhook, in-app notification, or campaign dispatch.')
        ->and($report)->toContain('Guidance implies copy telemetry persistence.')
        ->and($report)->toContain('Guidance implies short-link or QR asset generation.')
        ->and($report)->toContain('pending-human-guidance-intake')
        ->and($report)->toContain('Do not mark Pass, Blocked, or Fail without evidence from a reviewer.')
        ->and($report)->toContain('Cockpit Wave 60D — Manual Guidance Pending Acceptance Status / Closure')
        ->and($cockpitCompass)->toContain('Cockpit Wave 60C — Manual Guidance Acceptance Decision Policy')
        ->and($cockpitCompass)->toContain('reports/359-wave-60c-manual-guidance-acceptance-decision-policy.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 60C — Manual Guidance Acceptance Decision Policy')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/359-wave-60c-manual-guidance-acceptance-decision-policy.md');
});
