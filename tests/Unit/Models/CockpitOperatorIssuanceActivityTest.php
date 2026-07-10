<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;

it('maps cockpit operator issuance activity to the durable activity table', function () {
    $activity = new CockpitOperatorIssuanceActivity;

    expect($activity->getTable())->toBe('x_change_cockpit_operator_issuance_activities');
});

it('allows only operator-safe durable activity attributes for mass assignment', function () {
    $activity = new CockpitOperatorIssuanceActivity;

    expect($activity->getFillable())->toBe([
        'activity_id',
        'schema',
        'actor_id',
        'actor_label',
        'source',
        'subject_type',
        'subject_reference',
        'status',
        'severity',
        'occurred_at',
        'idempotency_key_hash',
        'correlation_id',
        'causation_id',
        'summary',
        'safe_context',
        'redaction_flags',
        'journal_handoff_status',
        'action_handoff_status',
        'feedback_handoff_status',
        'retention_until',
        'metadata',
    ])->not->toContain(
        'raw_payload',
        'provider_payload',
        'wallet',
        'balance',
        'account_number',
        'recipient_secret',
        'otp',
        'funding_source',
        'idempotency_key',
    );
});

it('casts activity context flags metadata and retention timestamps', function () {
    $activity = new CockpitOperatorIssuanceActivity([
        'activity_id' => 'activity-1',
        'occurred_at' => '2026-07-10T09:00:00+08:00',
        'retention_until' => '2026-08-09T09:00:00+08:00',
        'safe_context' => ['amount' => '100.00', 'currency' => 'PHP'],
        'redaction_flags' => ['raw_payloads_exposed' => false],
        'metadata' => ['schema' => 'x-change.cockpit.operator-issuance-activity-record.v1'],
    ]);

    expect($activity->occurred_at)->toBeInstanceOf(Carbon::class)
        ->and($activity->retention_until)->toBeInstanceOf(Carbon::class)
        ->and($activity->safe_context)->toBe(['amount' => '100.00', 'currency' => 'PHP'])
        ->and($activity->redaction_flags)->toBe(['raw_payloads_exposed' => false])
        ->and($activity->metadata)->toBe(['schema' => 'x-change.cockpit.operator-issuance-activity-record.v1']);
});
