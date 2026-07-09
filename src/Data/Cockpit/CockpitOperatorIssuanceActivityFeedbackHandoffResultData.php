<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityFeedbackHandoffResultData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity-feedback-handoff.v1',
        public readonly string $status = 'not_wired',
        public readonly ?string $activity_id = null,
        public readonly ?string $correlation_id = null,
        public readonly ?string $feedback_intent_id = null,
        public readonly ?string $delivery_plan_id = null,
        public readonly ?string $delivery_receipt_id = null,
        public readonly bool $feedback_required = false,
        public readonly bool $sends_feedback = false,
        public readonly string $source = 'null-cockpit-operator-issuance-activity-feedback-handoff',
        public readonly string $reason = 'x-feedback handoff is not wired. Cockpit does not send notifications in this boundary.',
        public readonly array $metadata = [],
    ) {}
}
