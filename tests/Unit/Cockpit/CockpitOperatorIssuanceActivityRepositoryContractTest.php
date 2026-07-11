<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivitySearchFilterData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\InMemoryCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRepository;

it('defines a durable operator issuance activity record dto without raw payload fields', function () {
    $record = new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-1',
        actor_id: 'operator-1',
        actor_label: 'Treasury Operations',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-1234',
        status: 'issued',
        severity: 'info',
        occurred_at: '2026-07-10T09:00:00+08:00',
        idempotency_key_hash: 'idem-hash-1',
        correlation_id: 'corr-1',
        causation_id: 'cause-1',
        summary: 'Pay Code PC-1234 issued',
        safe_context: [
            'amount' => '100.00',
            'currency' => 'PHP',
        ],
        redaction_flags: [
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
        ],
        journal_handoff_status: 'not_wired',
        action_handoff_status: 'not_wired',
        feedback_handoff_status: 'not_wired',
        retention_until: '2026-08-10T09:00:00+08:00',
        metadata: [
            'schema' => 'x-change.cockpit.operator-issuance-activity-record.v1',
        ],
    );

    expect($record->toArray())->toBe([
        'activity_id' => 'activity-1',
        'schema' => 'x-change.cockpit.operator-issuance-activity-record.v1',
        'actor_id' => 'operator-1',
        'actor_label' => 'Treasury Operations',
        'source' => 'cockpit.quick-generate',
        'subject_type' => 'pay_code',
        'subject_reference' => 'PC-1234',
        'status' => 'issued',
        'severity' => 'info',
        'occurred_at' => '2026-07-10T09:00:00+08:00',
        'idempotency_key_hash' => 'idem-hash-1',
        'correlation_id' => 'corr-1',
        'causation_id' => 'cause-1',
        'summary' => 'Pay Code PC-1234 issued',
        'safe_context' => [
            'amount' => '100.00',
            'currency' => 'PHP',
        ],
        'redaction_flags' => [
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
        ],
        'journal_handoff_status' => 'not_wired',
        'action_handoff_status' => 'not_wired',
        'feedback_handoff_status' => 'not_wired',
        'retention_until' => '2026-08-10T09:00:00+08:00',
        'metadata' => [
            'schema' => 'x-change.cockpit.operator-issuance-activity-record.v1',
        ],
    ])
        ->and($record->toArray())->not->toHaveKeys([
            'raw_payload',
            'provider_payload',
            'wallet',
            'balance',
            'account_number',
            'recipient_secret',
            'otp',
            'funding_source',
        ]);
});

it('binds the durable activity repository contract to a null non-persistent implementation', function () {
    $repository = app(CockpitOperatorIssuanceActivityRepositoryContract::class);

    expect($repository)
        ->toBeInstanceOf(CockpitOperatorIssuanceActivityRepositoryContract::class)
        ->toBeInstanceOf(NullCockpitOperatorIssuanceActivityRepository::class);
});

it('keeps the null durable activity repository non-persistent and read-model safe', function () {
    $repository = app(CockpitOperatorIssuanceActivityRepositoryContract::class);

    $record = new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-1',
        actor_id: 'operator-1',
        actor_label: 'Treasury Operations',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-1234',
        status: 'issued',
        occurred_at: '2026-07-10T09:00:00+08:00',
        summary: 'Pay Code PC-1234 issued',
    );

    expect($repository->record($record))->toBe($record)
        ->and($repository->findByActivityId('activity-1'))->toBeNull()
        ->and($repository->recent(new CockpitReadModelQueryData(
            operatorId: 'operator-1',
            include: ['operator_issuance_activity'],
            correlationId: 'corr-1',
        )))->toBe([]);
});

it('stores and retrieves operator issuance activity records in memory', function () {
    $repository = new InMemoryCockpitOperatorIssuanceActivityRepository;

    $first = cockpitActivityRecord(
        activityId: 'activity-1',
        actorId: 'operator-1',
        subjectReference: 'PC-1001',
        occurredAt: '2026-07-10T09:00:00+08:00',
        correlationId: 'corr-1',
    );

    $second = cockpitActivityRecord(
        activityId: 'activity-2',
        actorId: 'operator-1',
        subjectReference: 'PC-1002',
        occurredAt: '2026-07-10T09:05:00+08:00',
        correlationId: 'corr-1',
    );

    expect($repository->record($first))->toBe($first)
        ->and($repository->record($second))->toBe($second)
        ->and($repository->findByActivityId('activity-1'))->toBe($first)
        ->and($repository->findByActivityId('missing'))->toBeNull();
});

it('returns recent in-memory activity records newest first with query filtering and caps', function () {
    $repository = new InMemoryCockpitOperatorIssuanceActivityRepository;

    $repository->record(cockpitActivityRecord(
        activityId: 'activity-1',
        actorId: 'operator-1',
        subjectReference: 'PC-1001',
        occurredAt: '2026-07-10T09:00:00+08:00',
        correlationId: 'corr-1',
    ));
    $repository->record(cockpitActivityRecord(
        activityId: 'activity-2',
        actorId: 'operator-2',
        subjectReference: 'PC-1002',
        occurredAt: '2026-07-10T09:05:00+08:00',
        correlationId: 'corr-2',
    ));
    $repository->record(cockpitActivityRecord(
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

    $uncapped = $repository->recent(new CockpitReadModelQueryData(
        operatorId: 'operator-1',
        include: ['operator_issuance_activity'],
        correlationId: 'corr-1',
    ), limit: 10);

    expect(array_map(
        fn (CockpitOperatorIssuanceActivityRecordData $record): string => $record->activity_id,
        $uncapped,
    ))->toBe(['activity-3', 'activity-1']);
});

it('overwrites duplicate in-memory activity ids without creating duplicate recent records', function () {
    $repository = new InMemoryCockpitOperatorIssuanceActivityRepository;

    $repository->record(cockpitActivityRecord(
        activityId: 'activity-1',
        actorId: 'operator-1',
        subjectReference: 'PC-1001',
        occurredAt: '2026-07-10T09:00:00+08:00',
        correlationId: 'corr-1',
        summary: 'Original summary',
    ));
    $updated = cockpitActivityRecord(
        activityId: 'activity-1',
        actorId: 'operator-1',
        subjectReference: 'PC-1001',
        occurredAt: '2026-07-10T09:15:00+08:00',
        correlationId: 'corr-1',
        summary: 'Updated summary',
    );

    $repository->record($updated);

    $recent = $repository->recent(new CockpitReadModelQueryData(
        operatorId: 'operator-1',
        include: ['operator_issuance_activity'],
        correlationId: 'corr-1',
    ));

    expect($repository->findByActivityId('activity-1'))->toBe($updated)
        ->and($recent)->toHaveCount(1)
        ->and($recent[0]->summary)->toBe('Updated summary');
});

it('filters in-memory activity records by search text status and handoff status', function () {
    $repository = new InMemoryCockpitOperatorIssuanceActivityRepository;

    $repository->record(cockpitActivityRecord(
        activityId: 'activity-1',
        actorId: 'operator-1',
        subjectReference: 'PC-SEARCH-1',
        occurredAt: '2026-07-10T09:00:00+08:00',
        correlationId: 'corr-1',
        summary: 'Money Changer Pay Code issued',
        journalHandoffStatus: 'recorded',
    ));
    $repository->record(cockpitActivityRecord(
        activityId: 'activity-2',
        actorId: 'operator-1',
        subjectReference: 'PC-SEARCH-2',
        occurredAt: '2026-07-10T09:05:00+08:00',
        correlationId: 'corr-2',
        summary: 'Failed issuance activity',
        status: 'failed',
        journalHandoffStatus: 'not_wired',
    ));
    $repository->record(cockpitActivityRecord(
        activityId: 'activity-3',
        actorId: 'operator-1',
        subjectReference: 'PC-SEARCH-3',
        occurredAt: '2026-07-10T09:10:00+08:00',
        correlationId: 'corr-3',
        summary: 'OFW remittance Pay Code issued',
        journalHandoffStatus: 'not_wired',
    ));

    $records = $repository->recent(new CockpitReadModelQueryData(
        operatorId: 'operator-1',
        include: ['operator_issuance_activity'],
        operatorActivityFilters: CockpitOperatorIssuanceActivitySearchFilterData::normalize(
            search: 'money changer',
            statuses: ['issued'],
            handoffStatuses: ['recorded'],
        ),
    ));

    expect($records)->toHaveCount(1)
        ->and($records[0]->activity_id)->toBe('activity-1');
});

function cockpitActivityRecord(
    string $activityId,
    string $actorId,
    string $subjectReference,
    string $occurredAt,
    string $correlationId,
    string $summary = 'Pay Code issued',
    string $status = 'issued',
    string $journalHandoffStatus = 'not_wired',
): CockpitOperatorIssuanceActivityRecordData {
    return new CockpitOperatorIssuanceActivityRecordData(
        activity_id: $activityId,
        actor_id: $actorId,
        actor_label: 'Treasury Operations',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: $subjectReference,
        status: $status,
        occurred_at: $occurredAt,
        correlation_id: $correlationId,
        summary: $summary,
        safe_context: [
            'amount' => '100.00',
            'currency' => 'PHP',
        ],
        journal_handoff_status: $journalHandoffStatus,
    );
}
