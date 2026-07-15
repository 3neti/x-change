<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XAction\Contracts\ActionHostComposerContract;
use LBHurtado\XAction\Data\ActionContextData;
use LBHurtado\XAction\Data\ActionHostActionData;
use LBHurtado\XAction\Data\ActionSubjectData;
use LBHurtado\XChange\Contracts\ExecutionResultActionHandoffContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;

class XActionExecutionResultActionHandoff implements ExecutionResultActionHandoffContract
{
    private const EventOrState = 'execution.result.recorded';

    public function __construct(
        private readonly ActionHostComposerContract $composer,
    ) {}

    public function handoff(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffResultData
    {
        $correlationId = $this->correlationId($context);

        $composed = $this->composer->compose(
            eventOrState: self::EventOrState,
            subject: $this->subject($result, $context),
            context: $this->context($context),
            correlationId: $correlationId,
            causationId: $result->execution_id,
            includeDiagnostics: false,
        );

        $actions = array_values(array_map(
            fn (ActionHostActionData $action): array => $this->safeAction($action),
            $composed->actions,
        ));

        return new ExecutionResultHandoffResultData(
            target: 'action',
            status: $actions === [] ? 'no_actions' : 'composed',
            execution_id: $result->execution_id,
            voucher_code: $context->voucherCode,
            correlation_id: $correlationId,
            blocking: false,
            performed_side_effect: false,
            source: 'x-action-execution-result-action-handoff',
            reason: $actions === []
                ? 'x-action composed no continuation actions for this execution result.'
                : 'x-action composed presentation-only continuation action hints for this execution result.',
            metadata: [
                'event_or_state' => $composed->event_or_state,
                'actions' => $actions,
                'composition' => is_array($composed->meta['composition'] ?? null)
                    ? $composed->meta['composition']
                    : [],
                'safe_diagnostics' => is_array($composed->meta['safe_diagnostics'] ?? null)
                    ? $composed->meta['safe_diagnostics']
                    : [],
            ],
        );
    }

    private function subject(ExecutionResultData $result, ExecutionContextData $context): ActionSubjectData
    {
        return new ActionSubjectData(
            type: 'execution_result',
            id: $result->execution_id,
            attributes: [
                'execution_id' => $result->execution_id,
                'voucher_code' => $context->voucherCode,
                'driver' => $result->driver,
            ],
            state: [
                'status' => $result->status,
                'successful' => $result->successful,
            ],
            meta: [
                'failure' => $result->failure,
                'has_events' => $result->events !== [],
                'has_provider_references' => $result->providerReferences !== [],
                'has_reconciliation' => $result->reconciliation !== [],
                'has_children' => $result->children !== [],
                'instruction_driver' => $context->instruction?->driver,
            ],
        );
    }

    private function context(ExecutionContextData $context): ActionContextData
    {
        return new ActionContextData(
            actor_type: 'system',
            actor_id: 'x-change',
            feature_profile: 'execution',
            surface: 'execution',
            channel: 'system',
            capabilities: [
                'execution.result.view',
                'execution.continuation.plan',
                'cockpit.pay-code.open',
            ],
            meta: [
                'voucher_code' => $context->voucherCode,
                'presentation_only' => true,
                'executes_action' => false,
                'records_lifecycle' => false,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function safeAction(ActionHostActionData $action): array
    {
        return [
            'key' => $action->action->key,
            'label' => $action->action->label,
            'intent' => $action->action->intent,
            'description' => $action->action->description,
            'run_id' => $action->run->run_id,
            'target' => [
                'type' => $action->target_resolution->type,
                'url' => $action->target_resolution->url,
                'method' => $action->target_resolution->method,
                'redirectable' => $action->target_resolution->redirectable,
                'external' => $action->target_resolution->external,
            ],
            'run_semantics' => is_array($action->meta['run_semantics'] ?? null)
                ? $action->meta['run_semantics']
                : [],
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
