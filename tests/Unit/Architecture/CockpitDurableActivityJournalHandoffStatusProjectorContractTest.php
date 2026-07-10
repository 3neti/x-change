<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff status projector contract', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/082-durable-activity-journal-handoff-status-projector-contract.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $contract = file_get_contents($packageRoot.'/src/Contracts/CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract.php');
    $data = file_get_contents($packageRoot.'/src/Data/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData.php');
    $projector = file_get_contents($packageRoot.'/src/Services/Cockpit/NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector.php');
    $provider = file_get_contents($packageRoot.'/src/Providers/XChangeServiceProvider.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4F — Durable Activity Journal Handoff Status Projector Contract')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData')
        ->and($report)->toContain('NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector')
        ->and($report)->toContain('No durable activity row mutation')
        ->and($report)->toContain('No UI changes')
        ->and($report)->toContain('Cockpit Mutation Wave 4G — Durable Activity Journal Handoff Status Persistence Adapter')
        ->and($contract)->toContain('interface CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract')
        ->and($contract)->toContain('project(CockpitOperatorIssuanceActivityJournalHandoffResultData $result)')
        ->and($data)->toContain('class CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData')
        ->and($data)->toContain('persists_status')
        ->and($projector)->toContain('class NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector')
        ->and($projector)->not->toContain('CockpitOperatorIssuanceActivityRepositoryContract')
        ->and($provider)->toContain('CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4F — Durable Activity Journal Handoff Status Projector Contract')
        ->and($cockpitCompass)->toContain('reports/082-durable-activity-journal-handoff-status-projector-contract.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4F — Durable Activity Journal Handoff Status Projector Contract')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/082-durable-activity-journal-handoff-status-projector-contract.md');
});
