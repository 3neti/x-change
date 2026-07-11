<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectionData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;

class DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector implements CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract
{
    public function project(CockpitOperatorIssuanceActivityFeedbackHandoffResultData $result): CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectionData
    {
        if ($result->activity_id === null || trim($result->activity_id) === '') {
            return $this->notPersisted($result, 'missing_activity_id', 'Feedback handoff status was not persisted because the handoff result has no activity ID.');
        }

        $activity = CockpitOperatorIssuanceActivity::query()
            ->where('activity_id', $result->activity_id)
            ->first();

        if (! $activity instanceof CockpitOperatorIssuanceActivity) {
            return $this->notPersisted($result, 'missing_activity', 'Feedback handoff status was not persisted because the durable activity row was not found.');
        }

        $activity->forceFill([
            'feedback_handoff_status' => $result->status,
            'metadata' => [
                ...($activity->metadata ?? []),
                'feedback_handoff' => $this->feedbackHandoffMetadata($result),
            ],
        ])->save();

        return new CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectionData(
            status: 'persisted',
            activity_id: $result->activity_id,
            correlation_id: $result->correlation_id,
            feedback_handoff_status: $result->status,
            feedback_intent_id: $result->feedback_intent_id,
            delivery_plan_id: $result->delivery_plan_id,
            delivery_receipt_id: $result->delivery_receipt_id,
            persists_status: true,
            source: 'database-cockpit-operator-issuance-activity-feedback-handoff-status-projector',
            reason: 'Feedback handoff status was persisted to the durable Cockpit activity row.',
            metadata: [
                'feedback_handoff' => $this->feedbackHandoffMetadata($result),
            ],
        );
    }

    private function notPersisted(
        CockpitOperatorIssuanceActivityFeedbackHandoffResultData $result,
        string $status,
        string $reason,
    ): CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectionData {
        return new CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectionData(
            status: $status,
            activity_id: $result->activity_id,
            correlation_id: $result->correlation_id,
            feedback_handoff_status: $result->status,
            feedback_intent_id: $result->feedback_intent_id,
            delivery_plan_id: $result->delivery_plan_id,
            delivery_receipt_id: $result->delivery_receipt_id,
            persists_status: false,
            source: 'database-cockpit-operator-issuance-activity-feedback-handoff-status-projector',
            reason: $reason,
            metadata: [
                'feedback_handoff' => $this->feedbackHandoffMetadata($result),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackHandoffMetadata(CockpitOperatorIssuanceActivityFeedbackHandoffResultData $result): array
    {
        return [
            'status' => $result->status,
            'feedback_intent_id' => $result->feedback_intent_id,
            'delivery_plan_id' => $result->delivery_plan_id,
            'delivery_receipt_id' => $result->delivery_receipt_id,
            'feedback_required' => $result->feedback_required,
            'sends_feedback' => $result->sends_feedback,
            'source' => $result->source,
            'reason' => $result->reason,
            'metadata' => $this->safeMetadata($result->metadata),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function safeMetadata(array $metadata): array
    {
        $safe = array_intersect_key($metadata, array_flip([
            'intent_key',
            'event_type',
            'delivery_boundary',
            'planned_deliveries',
            'channels',
            'plan_items',
            'composition',
            'exception',
        ]));

        if (isset($safe['plan_items']) && is_array($safe['plan_items'])) {
            $safe['plan_items'] = array_values(array_map(
                fn (mixed $item): array => $this->safePlanItem($item),
                $safe['plan_items'],
            ));
        }

        return $safe;
    }

    /**
     * @return array<string, mixed>
     */
    private function safePlanItem(mixed $item): array
    {
        if (! is_array($item)) {
            return [];
        }

        return array_intersect_key($item, array_flip([
            'intent_key',
            'recipient_type',
            'recipient_id',
            'channel',
            'status',
            'priority',
            'correlation_id',
            'causation_id',
        ]));
    }
}
