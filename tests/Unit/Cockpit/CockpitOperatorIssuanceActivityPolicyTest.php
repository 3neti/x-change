<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRedactionPolicyContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRetentionPolicyContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitOperatorIssuanceActivityRedactionPolicy;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitOperatorIssuanceActivityRetentionPolicy;

it('binds operator issuance activity redaction and retention policies to safe defaults', function () {
    expect(app(CockpitOperatorIssuanceActivityRedactionPolicyContract::class))
        ->toBeInstanceOf(DefaultCockpitOperatorIssuanceActivityRedactionPolicy::class)
        ->and(app(CockpitOperatorIssuanceActivityRetentionPolicyContract::class))
        ->toBeInstanceOf(DefaultCockpitOperatorIssuanceActivityRetentionPolicy::class);
});

it('redacts sensitive activity context and metadata without removing safe display fields', function () {
    $policy = new DefaultCockpitOperatorIssuanceActivityRedactionPolicy;

    $record = new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-1',
        actor_id: 'operator-1',
        subject_reference: 'PC-1001',
        occurred_at: '2026-07-10T09:00:00+08:00',
        summary: 'Pay Code issued',
        safe_context: [
            'amount' => '100.00',
            'currency' => 'PHP',
            'wallet' => [
                'account_number' => '1234567890',
                'available_balance' => '10000.00',
            ],
            'recipient' => [
                'mobile' => '09173011987',
                'recipient_secret' => 'otp-secret',
            ],
            'provider_payload' => [
                'authorization' => 'Bearer secret-token',
            ],
        ],
        redaction_flags: [
            'raw_payloads_exposed' => true,
            'provider_payloads_exposed' => true,
            'wallet_data_exposed' => true,
            'recipient_secrets_exposed' => true,
        ],
        metadata: [
            'schema' => 'x-change.cockpit.operator-issuance-activity-record.v1',
            'raw_payload' => [
                'password' => 'secret',
            ],
            'idempotency_token' => 'secret-token',
        ],
    );

    $redacted = $policy->redact($record);

    expect($redacted->safe_context)->toBe([
        'amount' => '100.00',
        'currency' => 'PHP',
        'wallet' => '[redacted]',
        'recipient' => [
            'mobile' => '[redacted]',
            'recipient_secret' => '[redacted]',
        ],
        'provider_payload' => '[redacted]',
    ])
        ->and($redacted->metadata)->toBe([
            'schema' => 'x-change.cockpit.operator-issuance-activity-record.v1',
            'raw_payload' => '[redacted]',
            'idempotency_token' => '[redacted]',
        ])
        ->and($redacted->redaction_flags)->toBe([
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
        ])
        ->and($redacted->activity_id)->toBe('activity-1')
        ->and($redacted->subject_reference)->toBe('PC-1001')
        ->and($redacted->summary)->toBe('Pay Code issued');
});

it('derives retention from occurrence time and preserves explicit retention deadlines', function () {
    $policy = new DefaultCockpitOperatorIssuanceActivityRetentionPolicy(days: 30);

    $record = new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-1',
        occurred_at: '2026-07-10T09:00:00+08:00',
    );

    $explicit = new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-2',
        occurred_at: '2026-07-10T09:00:00+08:00',
        retention_until: '2026-12-31T00:00:00+08:00',
    );

    expect($policy->retentionUntil($record))->toBe('2026-08-09T09:00:00+08:00')
        ->and($policy->retentionUntil($explicit))->toBe('2026-12-31T00:00:00+08:00');
});

it('marks only redacted identifiable activity records as retainable', function () {
    $policy = new DefaultCockpitOperatorIssuanceActivityRetentionPolicy;

    $retainable = new CockpitOperatorIssuanceActivityRecordData(activity_id: 'activity-1');
    $emptyId = new CockpitOperatorIssuanceActivityRecordData(activity_id: '');
    $unsafe = new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-2',
        redaction_flags: [
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => true,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
        ],
    );

    expect($policy->isRetainable($retainable))->toBeTrue()
        ->and($policy->isRetainable($emptyId))->toBeFalse()
        ->and($policy->isRetainable($unsafe))->toBeFalse();
});
