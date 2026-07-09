<?php

declare(strict_types=1);

it('documents the cockpit operator issuance activity presentation closure without side effects', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/056-operator-issuance-activity-presentation-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2F — Activity Presentation Closure')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('DefaultCockpitOperatorIssuanceActivityPresenter')
        ->and($report)->toContain('presentation_only: true')
        ->and($report)->toContain('does not invoke journal, action, or feedback handoffs')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2F — Activity Presentation Closure')
        ->and($cockpitCompass)->toContain('reports/056-operator-issuance-activity-presentation-closure.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2F — Activity Presentation Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/056-operator-issuance-activity-presentation-closure.md');
});
