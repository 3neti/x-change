<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityJournalPayloadData extends Data
{
    /**
     * @param  array{type: string, id: string|null}  $actor
     * @param  array{type: string, reference: string}  $subject
     * @param  array{activity_id: string, correlation_id: string|null, causation_id: string}  $references
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity-journal-payload.v1',
        public readonly string $event_name = 'cockpit.operator_issuance_activity.recorded',
        public readonly string $domain = 'cockpit',
        public readonly string $idempotency_key = '',
        public readonly array $actor = [
            'type' => 'operator',
            'id' => null,
        ],
        public readonly array $subject = [
            'type' => 'pay_code',
            'reference' => '',
        ],
        public readonly array $references = [
            'activity_id' => '',
            'correlation_id' => null,
            'causation_id' => '',
        ],
        public readonly array $payload = [],
        public readonly array $metadata = [],
    ) {}
}
