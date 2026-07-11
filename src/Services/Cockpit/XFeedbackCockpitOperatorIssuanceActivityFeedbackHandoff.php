<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XFeedback\Contracts\FeedbackDispatchPreparerContract;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackChannelSelectionPolicyData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryPlanItemData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;

class XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff implements CockpitOperatorIssuanceActivityFeedbackHandoffContract
{
    private const IntentKey = 'cockpit.operator_issuance_activity.recorded';

    private const EventType = 'cockpit.operator_issuance_activity.recorded';

    public function __construct(
        private readonly FeedbackDispatchPreparerContract $preparer,
    ) {}

    public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityFeedbackHandoffResultData
    {
        $preparation = $this->preparer->prepare(
            intent: $this->intent($activity),
            policy: new FeedbackChannelSelectionPolicyData(
                allowed_channels: ['in_app'],
                preferred_channels: ['in_app'],
                profile: 'cockpit',
                meta: [
                    'delivery_boundary' => 'prepare_only',
                    'sends_feedback' => false,
                ],
            ),
        );

        $items = array_values(array_map(
            fn (FeedbackDeliveryPlanItemData $item): array => $this->safePlanItem($item),
            $preparation->plan->items,
        ));

        return new CockpitOperatorIssuanceActivityFeedbackHandoffResultData(
            status: $items === [] ? 'no_delivery_plan' : 'planned',
            activity_id: $activity->id,
            correlation_id: $activity->correlation_id,
            feedback_intent_id: $preparation->intent_key,
            delivery_plan_id: $this->deliveryPlanId($preparation->intent_key, $activity),
            delivery_receipt_id: null,
            feedback_required: false,
            sends_feedback: false,
            source: 'x-feedback-cockpit-operator-issuance-activity-feedback-handoff',
            reason: $items === []
                ? 'x-feedback prepared the operator activity intent without producing a delivery plan.'
                : 'x-feedback prepared an operator activity delivery plan without dispatching provider delivery.',
            metadata: [
                'intent_key' => $preparation->intent_key,
                'event_type' => $preparation->meta['event_type'] ?? self::EventType,
                'delivery_boundary' => 'prepare_only',
                'planned_deliveries' => count($items),
                'channels' => array_values(array_unique(array_column($items, 'channel'))),
                'plan_items' => $items,
                'composition' => [
                    'presentation_only' => true,
                    'delivery_only' => false,
                    'sends_feedback' => false,
                    'records_lifecycle' => false,
                    'owns_lifecycle_truth' => false,
                ],
            ],
        );
    }

    private function intent(CockpitOperatorIssuanceActivityItemData $activity): FeedbackIntentData
    {
        return FeedbackIntentData::forEvent(
            key: self::IntentKey,
            eventType: self::EventType,
            message: new FeedbackMessageData(
                title: 'Pay Code issued',
                body: sprintf('Pay Code %s was issued through Cockpit Quick Generate.', $activity->code),
                summary: sprintf('%s %s issued through Quick Generate.', $activity->currency, $activity->amount),
                variables: [
                    'code' => $activity->code,
                    'amount' => $activity->amount,
                    'currency' => $activity->currency,
                    'status' => $activity->status,
                ],
                actions: $activity->detail_href === null ? [] : [
                    [
                        'label' => 'Open Pay Code',
                        'href' => $activity->detail_href,
                        'type' => 'link',
                    ],
                ],
                meta: [
                    'presentation_only' => true,
                    'provider_delivery' => false,
                ],
            ),
            recipients: [
                new FeedbackRecipientData(
                    type: 'operator',
                    id: $activity->operator_id,
                    routes: [
                        'in_app' => $activity->operator_id,
                    ],
                    meta: [
                        'surface' => 'cockpit',
                    ],
                ),
            ],
            channels: [
                new FeedbackChannelData(
                    key: 'in_app',
                    priority: 100,
                    meta: [
                        'surface' => 'cockpit',
                        'dispatch_boundary' => 'prepare_only',
                    ],
                ),
            ],
            source: 'x-change.cockpit',
            correlationId: $activity->correlation_id,
            causationId: $activity->id,
            subjectType: 'pay_code',
            subjectId: $activity->code,
            meta: [
                'activity_id' => $activity->id,
                'route' => $activity->route,
                'status' => $activity->status,
                'detail_href' => $activity->detail_href,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function safePlanItem(FeedbackDeliveryPlanItemData $item): array
    {
        return [
            'intent_key' => $item->intent_key,
            'recipient_type' => $item->recipient->type,
            'recipient_id' => $item->recipient->id,
            'channel' => $item->channel,
            'status' => $item->status,
            'priority' => $item->priority,
            'correlation_id' => $item->correlation_id,
            'causation_id' => $item->causation_id,
        ];
    }

    private function deliveryPlanId(string $intentKey, CockpitOperatorIssuanceActivityItemData $activity): string
    {
        return 'plan-'.substr(hash('sha256', implode('|', [
            $intentKey,
            $activity->id,
            $activity->correlation_id ?? '',
            $activity->code,
        ])), 0, 24);
    }
}
