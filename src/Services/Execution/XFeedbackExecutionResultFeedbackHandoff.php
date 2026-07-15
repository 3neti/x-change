<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Contracts\ExecutionResultFeedbackHandoffContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XFeedback\Contracts\FeedbackDispatchPreparerContract;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackChannelSelectionPolicyData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryPlanItemData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;

class XFeedbackExecutionResultFeedbackHandoff implements ExecutionResultFeedbackHandoffContract
{
    private const IntentKey = 'execution.result.recorded';

    private const EventType = 'execution.result.recorded';

    public function __construct(
        private readonly FeedbackDispatchPreparerContract $preparer,
    ) {}

    public function handoff(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffResultData
    {
        $preparation = $this->preparer->prepare(
            intent: $this->intent($result, $context),
            policy: new FeedbackChannelSelectionPolicyData(
                allowed_channels: ['in_app'],
                preferred_channels: ['in_app'],
                profile: 'execution',
                meta: [
                    'delivery_boundary' => 'prepare_only',
                    'sends_feedback' => false,
                    'owns_lifecycle_truth' => false,
                ],
            ),
        );

        $items = array_values(array_map(
            fn (FeedbackDeliveryPlanItemData $item): array => $this->safePlanItem($item),
            $preparation->plan->items,
        ));

        return new ExecutionResultHandoffResultData(
            target: 'feedback',
            status: $items === [] ? 'no_delivery_plan' : 'planned',
            execution_id: $result->execution_id,
            voucher_code: $context->voucherCode,
            correlation_id: $this->correlationId($context),
            blocking: false,
            performed_side_effect: false,
            source: 'x-feedback-execution-result-feedback-handoff',
            reason: $items === []
                ? 'x-feedback prepared the execution result intent without producing a delivery plan.'
                : 'x-feedback prepared an execution result delivery plan without dispatching provider delivery.',
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

    private function intent(ExecutionResultData $result, ExecutionContextData $context): FeedbackIntentData
    {
        return FeedbackIntentData::forEvent(
            key: self::IntentKey,
            eventType: self::EventType,
            message: new FeedbackMessageData(
                title: 'Execution result recorded',
                body: sprintf(
                    'Execution %s for Pay Code %s finished with status %s.',
                    $result->execution_id,
                    $context->voucherCode,
                    $result->status,
                ),
                summary: sprintf('%s execution %s', $result->driver, $result->status),
                variables: [
                    'execution_id' => $result->execution_id,
                    'voucher_code' => $context->voucherCode,
                    'driver' => $result->driver,
                    'status' => $result->status,
                    'successful' => $result->successful,
                ],
                meta: [
                    'presentation_only' => true,
                    'provider_delivery' => false,
                    'lifecycle_truth_owner' => 'execution_engine',
                ],
            ),
            recipients: [
                new FeedbackRecipientData(
                    type: 'operator',
                    id: 'treasury-operations',
                    routes: [
                        'in_app' => 'treasury-operations',
                    ],
                    meta: [
                        'surface' => 'execution',
                    ],
                ),
            ],
            channels: [
                new FeedbackChannelData(
                    key: 'in_app',
                    priority: 100,
                    meta: [
                        'surface' => 'execution',
                        'dispatch_boundary' => 'prepare_only',
                    ],
                ),
            ],
            source: 'x-change.execution',
            correlationId: $this->correlationId($context),
            causationId: $result->execution_id,
            subjectType: 'pay_code',
            subjectId: $context->voucherCode,
            meta: [
                'execution_id' => $result->execution_id,
                'driver' => $result->driver,
                'status' => $result->status,
                'successful' => $result->successful,
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

    private function correlationId(ExecutionContextData $context): ?string
    {
        $correlation = $context->correlation['correlation_id']
            ?? $context->correlation['idempotency_key']
            ?? $context->voucherCode;

        return is_scalar($correlation) ? (string) $correlation : null;
    }
}
