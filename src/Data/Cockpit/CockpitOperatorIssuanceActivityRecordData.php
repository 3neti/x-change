<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityRecordData extends Data
{
    /**
     * @param  array<string, mixed>  $safe_context
     * @param  array<string, bool>  $redaction_flags
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $activity_id,
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity-record.v1',
        public readonly ?string $actor_id = null,
        public readonly ?string $actor_label = null,
        public readonly string $source = 'cockpit.quick-generate',
        public readonly string $subject_type = 'pay_code',
        public readonly ?string $subject_reference = null,
        public readonly string $status = 'recorded',
        public readonly string $severity = 'info',
        public readonly ?string $occurred_at = null,
        public readonly ?string $idempotency_key_hash = null,
        public readonly ?string $correlation_id = null,
        public readonly ?string $causation_id = null,
        public readonly ?string $summary = null,
        public readonly array $safe_context = [],
        public readonly array $redaction_flags = [
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
        ],
        public readonly string $journal_handoff_status = 'not_wired',
        public readonly string $action_handoff_status = 'not_wired',
        public readonly string $feedback_handoff_status = 'not_wired',
        public readonly ?string $retention_until = null,
        public readonly array $metadata = [],
    ) {}
}
