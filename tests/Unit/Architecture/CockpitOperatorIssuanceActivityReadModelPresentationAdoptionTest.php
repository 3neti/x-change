<?php

declare(strict_types=1);

it('documents the cockpit operator issuance activity read model presentation adoption boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/057-operator-issuance-activity-read-model-presentation-adoption.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2G — Activity Read Model Presentation Adoption')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('operator_issuance_activity_read_model')
        ->and($report)->toContain('presentations')
        ->and($report)->toContain('read-only dashboard prop')
        ->and($report)->toContain('no handoff invocation')
        ->and($report)->toContain('no journal writes')
        ->and($report)->toContain('no action execution')
        ->and($report)->toContain('no feedback delivery')
        ->and($report)->toContain('no money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2G — Activity Read Model Presentation Adoption')
        ->and($cockpitCompass)->toContain('reports/057-operator-issuance-activity-read-model-presentation-adoption.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2G — Activity Read Model Presentation Adoption')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/057-operator-issuance-activity-read-model-presentation-adoption.md');
});
