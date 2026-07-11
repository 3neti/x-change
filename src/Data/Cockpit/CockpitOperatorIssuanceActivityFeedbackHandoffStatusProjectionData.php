<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectionData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity-feedback-handoff-status-projection.v1',
        public readonly string $status = 'not_persisted',
        public readonly ?string $activity_id = null,
        public readonly ?string $correlation_id = null,
        public readonly string $feedback_handoff_status = 'not_wired',
        public readonly ?string $feedback_intent_id = null,
        public readonly ?string $delivery_plan_id = null,
        public readonly ?string $delivery_receipt_id = null,
        public readonly bool $persists_status = false,
        public readonly string $source = 'null-cockpit-operator-issuance-activity-feedback-handoff-status-projector',
        public readonly string $reason = 'Feedback handoff status persistence is not wired.',
        public readonly array $metadata = [],
    ) {}
}
