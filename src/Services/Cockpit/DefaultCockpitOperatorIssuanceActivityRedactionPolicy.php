<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRedactionPolicyContract;
use LBHurtado\XChange\Contracts\CockpitRedactorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Support\Cockpit\DefaultCockpitRedactor;

class DefaultCockpitOperatorIssuanceActivityRedactionPolicy implements CockpitOperatorIssuanceActivityRedactionPolicyContract
{
    /**
     * @var array<int, string>
     */
    private const ACTIVITY_SENSITIVE_KEYS = [
        'account_number',
        'available_balance',
        'balance',
        'client_secret',
        'funding_source',
        'idempotency_token',
        'integration_key',
        'merchant_key',
        'password',
        'provider_payload',
        'raw_payload',
        'recipient_secret',
        'wallet',
    ];

    public function __construct(
        private readonly CockpitRedactorContract $redactor = new DefaultCockpitRedactor,
    ) {}

    public function redact(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityRecordData
    {
        return new CockpitOperatorIssuanceActivityRecordData(
            activity_id: $record->activity_id,
            schema: $record->schema,
            actor_id: $record->actor_id,
            actor_label: $record->actor_label,
            source: $record->source,
            subject_type: $record->subject_type,
            subject_reference: $record->subject_reference,
            status: $record->status,
            severity: $record->severity,
            occurred_at: $record->occurred_at,
            idempotency_key_hash: $record->idempotency_key_hash,
            correlation_id: $record->correlation_id,
            causation_id: $record->causation_id,
            summary: $record->summary,
            safe_context: $this->redactor->redact($record->safe_context, self::ACTIVITY_SENSITIVE_KEYS),
            redaction_flags: [
                ...$record->redaction_flags,
                'raw_payloads_exposed' => false,
                'provider_payloads_exposed' => false,
                'wallet_data_exposed' => false,
                'recipient_secrets_exposed' => false,
            ],
            journal_handoff_status: $record->journal_handoff_status,
            action_handoff_status: $record->action_handoff_status,
            feedback_handoff_status: $record->feedback_handoff_status,
            retention_until: $record->retention_until,
            metadata: $this->redactor->redact($record->metadata, self::ACTIVITY_SENSITIVE_KEYS),
        );
    }
}
