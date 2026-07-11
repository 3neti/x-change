<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivitySearchFilterData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;

it('keeps operator issuance activity read models not wired when durable persistence is disabled', function () {
    $readModel = app(CockpitReadModelProviderContract::class)
        ->forOperatorIssuanceActivity(new CockpitReadModelQueryData);

    expect($readModel->status)->toBe('not_wired')
        ->and($readModel->authorized)->toBeFalse()
        ->and($readModel->items)->toBe([])
        ->and($readModel->presentations)->toBe([]);
});

it('hydrates operator issuance activity from the configured durable repository', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', DatabaseCockpitOperatorIssuanceActivityRecorder::class);

    $repository = app(DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository->record(new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-durable-1',
        actor_id: 'operator-1',
        actor_label: 'Treasury Operations',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-DURABLE-1',
        status: 'issued',
        occurred_at: '2026-07-10T09:00:00+08:00',
        correlation_id: 'corr-durable-1',
        summary: 'Pay Code PC-DURABLE-1 issued',
        safe_context: [
            'amount' => '25',
            'currency' => 'PHP',
            'route' => 'x-change.cockpit.quick-generate.store',
            'detail_href' => '/x/cockpit/pay-codes/PC-DURABLE-1',
        ],
        journal_handoff_status: 'not_wired',
        action_handoff_status: 'not_wired',
        feedback_handoff_status: 'not_wired',
        metadata: [
            'source' => 'x-change.cockpit',
        ],
    ));

    $readModel = app(CockpitReadModelProviderContract::class)
        ->forOperatorIssuanceActivity(new CockpitReadModelQueryData(
            operatorId: 'operator-1',
            include: ['operator_issuance_activity'],
            correlationId: 'corr-durable-1',
        ));

    expect($readModel->status)->toBe('available')
        ->and($readModel->authorized)->toBeTrue()
        ->and($readModel->source)->toBe('durable-operator-issuance-activity-read-model')
        ->and($readModel->items)->toHaveCount(1)
        ->and($readModel->items[0]->id)->toBe('activity-durable-1')
        ->and($readModel->items[0]->code)->toBe('PC-DURABLE-1')
        ->and($readModel->items[0]->amount)->toBe('25')
        ->and($readModel->items[0]->currency)->toBe('PHP')
        ->and($readModel->items[0]->detail_href)->toBe('/x/cockpit/pay-codes/PC-DURABLE-1')
        ->and($readModel->presentations)->toHaveCount(1)
        ->and($readModel->presentations[0]->title)->toBe('Pay Code PC-DURABLE-1 issued')
        ->and($readModel->presentations[0]->handoffs)->toBe([
            'journal' => 'not_wired',
            'action' => 'not_wired',
            'feedback' => 'not_wired',
        ])
        ->and($readModel->redactions['raw_payloads_exposed'])->toBeFalse()
        ->and($readModel->redactions['moves_money'])->toBeFalse();
});

it('exposes persisted journal handoff summary as safe read-only presentation metadata', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository = app(DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository->record(new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-journal-read-model-1',
        actor_id: 'operator-1',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-JOURNAL-READ-1',
        status: 'issued',
        occurred_at: '2026-07-10T09:00:00+08:00',
        correlation_id: 'corr-journal-read-1',
        safe_context: [
            'amount' => '50',
            'currency' => 'PHP',
            'route' => 'x-change.cockpit.quick-generate.store',
            'detail_href' => '/x/cockpit/pay-codes/PC-JOURNAL-READ-1',
        ],
        journal_handoff_status: 'recorded',
        action_handoff_status: 'not_wired',
        feedback_handoff_status: 'not_wired',
        metadata: [
            'journal_handoff' => [
                'status' => 'recorded',
                'journal_entry_id' => 'journal-entry-1',
                'writes_journal' => true,
                'source' => 'test-journal-handoff',
                'reason' => 'Journal handoff was recorded.',
                'metadata' => [
                    'reference_number' => 'XJ-1',
                    'event_type' => 'cockpit.operator_issuance_activity.recorded',
                    'provider_payload' => 'must-not-render',
                    'token' => 'must-not-render',
                ],
            ],
            'provider_payload' => 'must-not-render',
        ],
    ));

    $readModel = app(CockpitReadModelProviderContract::class)
        ->forOperatorIssuanceActivity(new CockpitReadModelQueryData(
            operatorId: 'operator-1',
            correlationId: 'corr-journal-read-1',
        ));

    expect($readModel->presentations)->toHaveCount(1)
        ->and($readModel->presentations[0]->handoffs)->toBe([
            'journal' => 'recorded',
            'action' => 'not_wired',
            'feedback' => 'not_wired',
        ])
        ->and($readModel->presentations[0]->metadata['journal_handoff'])->toBe([
            'status' => 'recorded',
            'journal_entry_id' => 'journal-entry-1',
            'writes_journal' => true,
            'source' => 'test-journal-handoff',
            'reason' => 'Journal handoff was recorded.',
            'metadata' => [
                'reference_number' => 'XJ-1',
                'event_type' => 'cockpit.operator_issuance_activity.recorded',
            ],
            'diagnostic' => [
                'classification' => 'recorded',
                'tone' => 'success',
                'label' => 'Journal recorded',
                'description' => 'The durable activity was handed to the journal and a journal entry identifier is available for read-only inspection.',
                'operator_action' => 'none',
                'read_only' => true,
                'retry_enabled' => false,
                'mutation_enabled' => false,
                'raw_payloads_exposed' => false,
            ],
        ])
        ->and($readModel->presentations[0]->metadata['provider_payload'] ?? null)->toBeNull()
        ->and($readModel->presentations[0]->metadata['journal_handoff']['metadata']['provider_payload'] ?? null)->toBeNull()
        ->and($readModel->presentations[0]->metadata['journal_handoff']['metadata']['token'] ?? null)->toBeNull();
});

it('exposes active operator activity search filters as read-only read model metadata', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository = app(DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository->record(new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-search-read-model-1',
        actor_id: 'operator-1',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-FILTER-1',
        status: 'issued',
        occurred_at: '2026-07-10T09:00:00+08:00',
        correlation_id: 'corr-filter-1',
        summary: 'Money Changer Pay Code issued',
        safe_context: [
            'amount' => '25',
            'currency' => 'PHP',
            'route' => 'x-change.cockpit.quick-generate.store',
        ],
        journal_handoff_status: 'recorded',
        action_handoff_status: 'not_wired',
        feedback_handoff_status: 'not_wired',
    ));

    $repository->record(new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-search-read-model-2',
        actor_id: 'operator-1',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-FILTER-2',
        status: 'failed',
        occurred_at: '2026-07-10T09:05:00+08:00',
        correlation_id: 'corr-filter-2',
        summary: 'Failed issuance activity',
        journal_handoff_status: 'not_wired',
        action_handoff_status: 'not_wired',
        feedback_handoff_status: 'not_wired',
    ));

    $readModel = app(CockpitReadModelProviderContract::class)
        ->forOperatorIssuanceActivity(new CockpitReadModelQueryData(
            operatorId: 'operator-1',
            include: ['operator_issuance_activity'],
            operatorActivityFilters: CockpitOperatorIssuanceActivitySearchFilterData::normalize(
                search: 'money changer',
                statuses: ['issued'],
                handoffStatuses: ['recorded'],
            ),
        ));

    expect($readModel->items)->toHaveCount(1)
        ->and($readModel->items[0]->id)->toBe('activity-search-read-model-1')
        ->and($readModel->search_filters)->toMatchArray([
            'schema' => 'x-change.cockpit.operator-issuance-activity-search-filter.v1',
            'status' => 'available',
            'read_only' => true,
            'search' => 'money changer',
            'statuses' => ['issued'],
            'handoff_statuses' => ['recorded'],
            'available_statuses' => ['issued'],
            'available_handoff_statuses' => ['recorded', 'not_wired'],
            'safety' => [
                'read_only' => true,
                'writes_journal' => false,
                'executes_actions' => false,
                'sends_feedback' => false,
                'moves_money' => false,
                'owns_lifecycle_truth' => false,
            ],
        ]);
});
