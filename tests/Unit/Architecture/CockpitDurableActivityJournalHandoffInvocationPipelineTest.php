<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff invocation pipeline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/084-durable-activity-journal-handoff-invocation-pipeline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $controller = file_get_contents($packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php');
    $pipeline = file_get_contents($packageRoot.'/src/Services/Cockpit/CockpitOperatorIssuanceActivityHandoffPipeline.php');
    $featureTest = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffInvocationPipelineTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4H — Durable Activity Journal Handoff Invocation Pipeline')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityHandoffPipeline')
        ->and($report)->toContain('record durable activity first')
        ->and($report)->toContain('invoke configured journal handoff')
        ->and($report)->toContain('project journal handoff status')
        ->and($report)->toContain('idempotency replays do not invoke handoff again')
        ->and($report)->toContain('No UI changes')
        ->and($report)->toContain('Cockpit Mutation Wave 4I — Durable Activity Journal Handoff Read Model Exposure')
        ->and($controller)->toContain('CockpitOperatorIssuanceActivityHandoffPipeline')
        ->and($controller)->toContain('processOperatorIssuanceActivity')
        ->and($pipeline)->toContain('class CockpitOperatorIssuanceActivityHandoffPipeline')
        ->and($pipeline)->toContain('CockpitOperatorIssuanceActivityRecorderContract')
        ->and($pipeline)->toContain('CockpitOperatorIssuanceActivityJournalHandoffContract')
        ->and($pipeline)->toContain('CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract')
        ->and($pipeline)->toContain('failed_non_blocking')
        ->and($featureTest)->toContain('invokes configured journal handoff and persists handoff status')
        ->and($featureTest)->toContain('does not invoke journal handoff again')
        ->and($featureTest)->toContain('keeps quick generate successful and projects failed handoff status')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4H — Durable Activity Journal Handoff Invocation Pipeline')
        ->and($cockpitCompass)->toContain('reports/084-durable-activity-journal-handoff-invocation-pipeline.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4H — Durable Activity Journal Handoff Invocation Pipeline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/084-durable-activity-journal-handoff-invocation-pipeline.md');
});
