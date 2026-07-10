<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal payload mapping baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/079-durable-activity-journal-payload-mapping-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $payload = file_get_contents($packageRoot.'/src/Data/Cockpit/CockpitOperatorIssuanceActivityJournalPayloadData.php');
    $mapper = file_get_contents($packageRoot.'/src/Services/Cockpit/CockpitOperatorIssuanceActivityJournalPayloadMapper.php');
    $test = file_get_contents($packageRoot.'/tests/Unit/Cockpit/CockpitOperatorIssuanceActivityJournalPayloadMapperTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4C — Durable Activity Journal Payload Mapping Baseline')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityJournalPayloadData')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityJournalPayloadMapper')
        ->and($report)->toContain('It is intentionally not an x-journal DTO yet')
        ->and($report)->toContain('No x-journal runtime calls')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('Cockpit Mutation Wave 4D — Durable Activity Journal Handoff Adapter Baseline')
        ->and($payload)->toContain('class CockpitOperatorIssuanceActivityJournalPayloadData')
        ->and($mapper)->toContain('class CockpitOperatorIssuanceActivityJournalPayloadMapper')
        ->and($mapper)->toContain('SENSITIVE_METADATA_KEYS')
        ->and($mapper)->not->toContain('LBHurtado\\XJournal')
        ->and($test)->toContain('raw_payload')
        ->and($test)->toContain('provider_payload')
        ->and($test)->toContain('funding_source')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4C — Durable Activity Journal Payload Mapping Baseline')
        ->and($cockpitCompass)->toContain('reports/079-durable-activity-journal-payload-mapping-baseline.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4C — Durable Activity Journal Payload Mapping Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/079-durable-activity-journal-payload-mapping-baseline.md');
});
