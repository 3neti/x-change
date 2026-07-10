<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;

it('keeps dashboard operator issuance activity props not wired by default', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.operator_issuance_activity_read_model.status', 'not_wired')
        ->assertJsonPath('props.operator_issuance_activity_read_model.authorized', false)
        ->assertJsonPath('props.operator_issuance_activity_read_model.items', [])
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations', []);
});

it('carries durable operator issuance activity into dashboard props when explicitly configured', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', DatabaseCockpitOperatorIssuanceActivityRecorder::class);

    $operator = actingAsTestUser();

    app(DatabaseCockpitOperatorIssuanceActivityRepository::class)->record(new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-dashboard-1',
        actor_id: (string) $operator->getAuthIdentifier(),
        actor_label: 'Treasury Operations',
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-DASHBOARD-1',
        status: 'issued',
        occurred_at: '2026-07-10T09:00:00+08:00',
        correlation_id: 'corr-dashboard-1',
        summary: 'Pay Code PC-DASHBOARD-1 issued',
        safe_context: [
            'amount' => '25',
            'currency' => 'PHP',
            'route' => 'x-change.cockpit.quick-generate.store',
            'detail_href' => '/x/cockpit/pay-codes/PC-DASHBOARD-1',
        ],
    ));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.operator_issuance_activity_read_model.status', 'available')
        ->assertJsonPath('props.operator_issuance_activity_read_model.authorized', true)
        ->assertJsonPath('props.operator_issuance_activity_read_model.source', 'durable-operator-issuance-activity-read-model')
        ->assertJsonPath('props.operator_issuance_activity_read_model.items.0.id', 'activity-dashboard-1')
        ->assertJsonPath('props.operator_issuance_activity_read_model.items.0.code', 'PC-DASHBOARD-1')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.title', 'Pay Code PC-DASHBOARD-1 issued')
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.safety.presentation_only', true)
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations.0.safety.moves_money', false)
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.raw_payload')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.provider_payload')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.wallet');
});
