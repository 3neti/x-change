<?php

declare(strict_types=1);

it('documents the cockpit durable activity dto and repository contract slice', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/064-durable-activity-dto-repository-contract.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3B — Durable Activity DTO and Repository Contract')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRecordData')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRepositoryContract')
        ->and($report)->toContain('NullCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('record(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityRecordData')
        ->and($report)->toContain('findByActivityId(string $activityId): ?CockpitOperatorIssuanceActivityRecordData')
        ->and($report)->toContain('recent(CockpitReadModelQueryData $query, int $limit = 25): array')
        ->and($report)->toContain('raw_payload')
        ->and($report)->toContain('provider_payload')
        ->and($report)->toContain('recipient_secret')
        ->and($report)->toContain('migrations')
        ->and($report)->toContain('Eloquent models')
        ->and($report)->toContain('database writes')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('UI changes')
        ->and($report)->toContain('No UI was changed in this slice')
        ->and($report)->toContain('3 passed, 14 assertions')
        ->and($report)->toContain('Cockpit Mutation Wave 3C — In-Memory Durable Activity Repository Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3B — Durable Activity DTO and Repository Contract')
        ->and($cockpitCompass)->toContain('reports/064-durable-activity-dto-repository-contract.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3B — Durable Activity DTO and Repository Contract')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/064-durable-activity-dto-repository-contract.md');
});
