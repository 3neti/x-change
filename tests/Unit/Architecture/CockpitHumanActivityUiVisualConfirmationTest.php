<?php

declare(strict_types=1);

it('documents the cockpit human activity ui visual confirmation checkpoint', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/062-human-activity-ui-visual-confirmation-record.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2L — Human Activity UI Visual Confirmation Record')
        ->and($report)->toContain('Status: Pass — accepted by human')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('Operator Issuance Activity')
        ->and($report)->toContain('Quick Generate evidence')
        ->and($report)->toContain('presentation-only')
        ->and($report)->toContain('No operator issuance activity available')
        ->and($report)->toContain('Activity recording is not wired yet')
        ->and($report)->toContain('I can confirm all are read only')
        ->and($report)->toContain('Pass — accepted by human')
        ->and($report)->toContain('not_wired')
        ->and($report)->toContain('Mutation blocked')
        ->and($report)->toContain('campaign-mutations-not-authorized')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('money movement')
        ->and($report)->toContain('Cockpit Mutation Wave 3A — Durable Activity Storage Boundary Plan')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2L — Human Activity UI Visual Confirmation Record')
        ->and($cockpitCompass)->toContain('reports/062-human-activity-ui-visual-confirmation-record.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2L — Human Activity UI Visual Confirmation Record')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/062-human-activity-ui-visual-confirmation-record.md');
});
