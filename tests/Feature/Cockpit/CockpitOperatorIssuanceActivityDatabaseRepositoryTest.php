<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRepository;

it('keeps the default durable activity repository binding null until explicitly wired', function () {
    expect(app(CockpitOperatorIssuanceActivityRepositoryContract::class))
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityRepository::class);
});

it('records redacted retainable activity through the database repository', function () {
    $repository = app(DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $record = cockpitDatabaseActivityRecord(
        activityId: 'activity-1',
        actorId: 'operator-1',
        subjectReference: 'PC-1001',
        occurredAt: '2026-07-10T09:00:00+08:00',
        correlationId: 'corr-1',
        safeContext: [
            'amount' => '100.00',
            'currency' => 'PHP',
            'provider_payload' => ['authorization' => 'Bearer secret-token'],
        ],
        metadata: [
            'raw_payload' => ['secret' => 'value'],
        ],
    );

    $stored = $repository->record($record);
    $found = $repository->findByActivityId('activity-1');

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(1)
        ->and($stored->activity_id)->toBe('activity-1')
        ->and($stored->safe_context)->toBe([
            'amount' => '100.00',
            'currency' => 'PHP',
            'provider_payload' => '[redacted]',
        ])
        ->and($stored->metadata)->toBe([
            'raw_payload' => '[redacted]',
        ])
        ->and($stored->redaction_flags)->toBe([
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
        ])
        ->and($stored->retention_until)->not->toBeNull()
        ->and($found?->activity_id)->toBe('activity-1');
});

it('upserts duplicate durable activity ids instead of creating duplicate rows', function () {
    $repository = app(DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository->record(cockpitDatabaseActivityRecord(
        activityId: 'activity-1',
        actorId: 'operator-1',
        subjectReference: 'PC-1001',
        occurredAt: '2026-07-10T09:00:00+08:00',
        correlationId: 'corr-1',
        summary: 'Original summary',
    ));

    $updated = $repository->record(cockpitDatabaseActivityRecord(
        activityId: 'activity-1',
        actorId: 'operator-1',
        subjectReference: 'PC-1001',
        occurredAt: '2026-07-10T09:05:00+08:00',
        correlationId: 'corr-1',
        summary: 'Updated summary',
    ));

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(1)
        ->and($updated->summary)->toBe('Updated summary')
        ->and($repository->findByActivityId('activity-1')?->summary)->toBe('Updated summary');
});

it('returns recent durable activity records newest first with query filters and caps', function () {
    $repository = app(DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository->record(cockpitDatabaseActivityRecord(
        activityId: 'activity-1',
        actorId: 'operator-1',
        subjectReference: 'PC-1001',
        occurredAt: '2026-07-10T09:00:00+08:00',
        correlationId: 'corr-1',
    ));
    $repository->record(cockpitDatabaseActivityRecord(
        activityId: 'activity-2',
        actorId: 'operator-2',
        subjectReference: 'PC-1002',
        occurredAt: '2026-07-10T09:05:00+08:00',
        correlationId: 'corr-2',
    ));
    $repository->record(cockpitDatabaseActivityRecord(
        activityId: 'activity-3',
        actorId: 'operator-1',
        subjectReference: 'PC-1003',
        occurredAt: '2026-07-10T09:10:00+08:00',
        correlationId: 'corr-1',
    ));

    $recent = $repository->recent(new CockpitReadModelQueryData(
        operatorId: 'operator-1',
        include: ['operator_issuance_activity'],
        correlationId: 'corr-1',
    ), limit: 1);

    expect($recent)->toHaveCount(1)
        ->and($recent[0]->activity_id)->toBe('activity-3');
});

it('does not persist non-retainable activity records', function () {
    $repository = app(DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $record = cockpitDatabaseActivityRecord(
        activityId: '',
        actorId: 'operator-1',
        subjectReference: 'PC-1001',
        occurredAt: '2026-07-10T09:00:00+08:00',
        correlationId: 'corr-1',
    );

    $repository->record($record);

    expect(CockpitOperatorIssuanceActivity::query()->count())->toBe(0)
        ->and($repository->findByActivityId(''))->toBeNull();
});

function cockpitDatabaseActivityRecord(
    string $activityId,
    string $actorId,
    string $subjectReference,
    string $occurredAt,
    string $correlationId,
    string $summary = 'Pay Code issued',
    array $safeContext = ['amount' => '100.00', 'currency' => 'PHP'],
    array $metadata = [],
): CockpitOperatorIssuanceActivityRecordData {
    return new CockpitOperatorIssuanceActivityRecordData(
        activity_id: $activityId,
        actor_id: $actorId,
        actor_label: 'Treasury Operations',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: $subjectReference,
        status: 'issued',
        occurred_at: $occurredAt,
        correlation_id: $correlationId,
        summary: $summary,
        safe_context: $safeContext,
        metadata: $metadata,
    );
}
